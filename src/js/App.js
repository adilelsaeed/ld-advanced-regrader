/**
 * Regrader admin app.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
} from '@wordpress/components';

import {
	fetchAttempts,
	fetchOptions,
	regradeBatch,
	regradeSelected,
} from './api';
import { runBatches } from './utils/batch';
import { summarize } from './utils/format';
import Filters from './components/Filters';
import AttemptsTable from './components/AttemptsTable';
import Pagination from './components/Pagination';
import ConfirmModal from './components/ConfirmModal';

const EMPTY_FILTERS = { quizId: '', groupId: '0', search: '' };

/**
 * Error message from an apiFetch rejection.
 *
 * @param {Object} error Error.
 * @return {string} Message.
 */
const errorMessage = ( error ) =>
	error?.message ||
	__( 'Something went wrong. Please try again.', 'ld-advanced-regrader' );

/**
 * @param {Object} props          Props.
 * @param {Object} props.settings `{ perPage, batchSize }` from PHP.
 * @return {Element} App.
 */
export default function App( { settings } ) {
	const perPage = settings.perPage || 20;

	const [ options, setOptions ] = useState( null );
	const [ filters, setFilters ] = useState( EMPTY_FILTERS );
	const [ query, setQuery ] = useState( null );
	const [ page, setPage ] = useState( 1 );
	const [ refreshKey, setRefreshKey ] = useState( 0 );
	const [ result, setResult ] = useState( { items: [], total: 0 } );
	const [ loading, setLoading ] = useState( false );
	const [ selected, setSelected ] = useState( () => new Set() );
	const [ updateCourse, setUpdateCourse ] = useState( false );
	const [ confirming, setConfirming ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ batch, setBatch ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const stopRef = useRef( false );

	useEffect( () => {
		fetchOptions()
			.then( setOptions )
			.catch( ( error ) =>
				setNotice( { status: 'error', message: errorMessage( error ) } )
			);
	}, [] );

	useEffect( () => {
		if ( ! query ) {
			return;
		}

		let cancelled = false;
		setLoading( true );

		fetchAttempts( { ...query, page, perPage } )
			.then( ( data ) => {
				if ( ! cancelled ) {
					setResult( data );
				}
			} )
			.catch( ( error ) => {
				if ( ! cancelled ) {
					setNotice( {
						status: 'error',
						message: errorMessage( error ),
					} );
				}
			} )
			.finally( () => ! cancelled && setLoading( false ) );

		return () => {
			cancelled = true;
		};
	}, [ query, page, perPage, refreshKey ] );

	const submitFilters = () => {
		setQuery( {
			quizId: Number( filters.quizId ),
			groupId: Number( filters.groupId ) || 0,
			search: filters.search.trim(),
		} );
		setPage( 1 );
		setSelected( new Set() );
		setNotice( null );
	};

	const toggle = useCallback( ( id ) => {
		setSelected( ( previous ) => {
			const next = new Set( previous );
			if ( next.has( id ) ) {
				next.delete( id );
			} else {
				next.add( id );
			}
			return next;
		} );
	}, [] );

	const toggleAll = ( checked ) => {
		setSelected( ( previous ) => {
			const next = new Set( previous );
			result.items.forEach( ( item ) =>
				checked
					? next.add( item.activity_id )
					: next.delete( item.activity_id )
			);
			return next;
		} );
	};

	const applySelected = async () => {
		setConfirming( null );
		setSaving( true );
		try {
			const response = await regradeSelected( {
				quizId: query.quizId,
				activityIds: [ ...selected ],
				updateCourse,
			} );
			setNotice( {
				status: 'success',
				message: sprintf(
					/* translators: %d: number of attempts. */
					_n(
						'Updated %d attempt.',
						'Updated %d attempts.',
						response.updated,
						'ld-advanced-regrader'
					),
					response.updated
				),
			} );
			setSelected( new Set() );
			setRefreshKey( ( key ) => key + 1 );
		} catch ( error ) {
			setNotice( { status: 'error', message: errorMessage( error ) } );
		} finally {
			setSaving( false );
		}
	};

	const applyAll = async () => {
		setConfirming( null );
		stopRef.current = false;
		setBatch( { processed: 0, total: result.total } );

		try {
			const summary = await runBatches(
				( offset ) =>
					regradeBatch( { ...query, offset, updateCourse } ),
				{
					onProgress: ( progress ) => setBatch( progress ),
					shouldStop: () => stopRef.current,
				}
			);

			const template = summary.stopped
				? /* translators: 1: attempts updated, 2: total attempts. */
				  __(
						'Stopped. Updated %1$d of %2$d attempts.',
						'ld-advanced-regrader'
				  )
				: /* translators: 1: attempts updated, 2: total attempts. */
				  __(
						'Done. Updated %1$d of %2$d attempts.',
						'ld-advanced-regrader'
				  );

			setNotice( {
				status: summary.stopped ? 'warning' : 'success',
				message: sprintf( template, summary.updated, summary.total ),
			} );
		} catch ( error ) {
			setNotice( { status: 'error', message: errorMessage( error ) } );
		} finally {
			setBatch( null );
			setSelected( new Set() );
			setRefreshKey( ( key ) => key + 1 );
		}
	};

	if ( ! options ) {
		return notice ? (
			<Notice status={ notice.status } isDismissible={ false }>
				{ notice.message }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const busy = loading || saving || batch !== null;
	const totalPages = Math.ceil( result.total / perPage );
	const pageSummary = summarize( result.items );

	return (
		<div className="ldar-app">
			<p className="ldar-intro">
				{ __(
					'Pick a quiz to compare each attempt’s saved score with the score it gets under the quiz’s current questions, answers and points. Nothing is saved until you confirm.',
					'ld-advanced-regrader'
				) }
			</p>

			<Filters
				options={ options }
				value={ filters }
				onChange={ setFilters }
				onSubmit={ submitFilters }
				isLoading={ loading }
			/>

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ query && ! loading && result.total === 0 && (
				<p className="ldar-empty">
					{ __(
						'No attempts match these filters.',
						'ld-advanced-regrader'
					) }
				</p>
			) }

			{ result.total > 0 && (
				<>
					<div className="ldar-summary">
						<span>
							{ sprintf(
								/* translators: %d: number of attempts. */
								_n(
									'%d matching attempt',
									'%d matching attempts',
									result.total,
									'ld-advanced-regrader'
								),
								result.total
							) }
						</span>
						<span>
							{ sprintf(
								/* translators: 1: changed rows, 2: rows going up, 3: rows going down. */
								__(
									'On this page: %1$d change (%2$d up, %3$d down)',
									'ld-advanced-regrader'
								),
								pageSummary.changed,
								pageSummary.up,
								pageSummary.down
							) }
						</span>
						{ loading && <Spinner /> }
					</div>

					<AttemptsTable
						items={ result.items }
						selected={ selected }
						onToggle={ toggle }
						onToggleAll={ toggleAll }
					/>

					<Pagination
						page={ page }
						totalPages={ totalPages }
						onChange={ setPage }
						disabled={ busy }
					/>

					<div className="ldar-actions">
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ __(
								'Mark the quiz complete for students who now pass',
								'ld-advanced-regrader'
							) }
							checked={ updateCourse }
							onChange={ setUpdateCourse }
							disabled={ busy }
						/>
						<Button
							variant="primary"
							disabled={ busy || selected.size === 0 }
							isBusy={ saving }
							onClick={ () => setConfirming( 'selected' ) }
						>
							{ sprintf(
								/* translators: %d: number of selected attempts. */
								__(
									'Save new scores for selected (%d)',
									'ld-advanced-regrader'
								),
								selected.size
							) }
						</Button>
						<Button
							variant="secondary"
							disabled={ busy }
							onClick={ () => setConfirming( 'all' ) }
						>
							{ sprintf(
								/* translators: %d: number of attempts. */
								__(
									'Save new scores for all %d matching',
									'ld-advanced-regrader'
								),
								result.total
							) }
						</Button>
					</div>
				</>
			) }

			{ batch && (
				<div className="ldar-batch" role="status" aria-live="polite">
					<progress
						value={ batch.processed }
						max={ batch.total || 1 }
					/>
					<span>
						{ sprintf(
							/* translators: 1: processed attempts, 2: total attempts. */
							__(
								'%1$d of %2$d attempts processed. Keep this tab open.',
								'ld-advanced-regrader'
							),
							batch.processed,
							batch.total
						) }
					</span>
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () => {
							stopRef.current = true;
						} }
					>
						{ __( 'Stop', 'ld-advanced-regrader' ) }
					</Button>
				</div>
			) }

			{ confirming && (
				<ConfirmModal
					title={ __( 'Save new scores?', 'ld-advanced-regrader' ) }
					confirmLabel={ __(
						'Save new scores',
						'ld-advanced-regrader'
					) }
					onConfirm={
						confirming === 'all' ? applyAll : applySelected
					}
					onCancel={ () => setConfirming( null ) }
				>
					<p>
						{ confirming === 'all'
							? sprintf(
									/* translators: %d: number of attempts. */
									__(
										'This recalculates and saves the score of all %d matching attempts, in batches. Students’ saved scores, pass/fail status and quiz statistics will change.',
										'ld-advanced-regrader'
									),
									result.total
							  )
							: sprintf(
									/* translators: %d: number of attempts. */
									__(
										'This recalculates and saves the score of the %d selected attempts. Students’ saved scores, pass/fail status and quiz statistics will change.',
										'ld-advanced-regrader'
									),
									selected.size
							  ) }
					</p>
					<p>
						{ __(
							'The original score is kept in the attempt’s data. Take a database backup first if you are regrading many attempts.',
							'ld-advanced-regrader'
						) }
					</p>
				</ConfirmModal>
			) }
		</div>
	);
}
