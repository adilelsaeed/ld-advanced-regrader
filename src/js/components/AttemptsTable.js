/**
 * Preview table: current score vs. recalculated score per attempt.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { dateI18n, getSettings } from '@wordpress/date';
import { formatDifference, formatPercent } from '../utils/format';

/**
 * Pass/fail label.
 *
 * @param {boolean} passed Whether it passes.
 * @return {string} Label.
 */
const passLabel = ( passed ) =>
	passed
		? __( 'Pass', 'ld-advanced-regrader' )
		: __( 'Fail', 'ld-advanced-regrader' );

/**
 * @param {Object}   props             Props.
 * @param {Object[]} props.items       Attempt rows.
 * @param {Set}      props.selected    Selected activity IDs.
 * @param {Function} props.onToggle    Called with an activity ID.
 * @param {Function} props.onToggleAll Called with true/false for the page.
 * @return {Element} Table.
 */
export default function AttemptsTable( {
	items,
	selected,
	onToggle,
	onToggleAll,
} ) {
	const allSelected =
		items.length > 0 &&
		items.every( ( item ) => selected.has( item.activity_id ) );
	const dateFormat = getSettings().formats.datetime;

	return (
		<table className="widefat striped ldar-table">
			<thead>
				<tr>
					<td className="check-column">
						<input
							type="checkbox"
							checked={ allSelected }
							onChange={ ( event ) =>
								onToggleAll( event.target.checked )
							}
							aria-label={ __(
								'Select all attempts on this page',
								'ld-advanced-regrader'
							) }
						/>
					</td>
					<th scope="col">
						{ __( 'Student', 'ld-advanced-regrader' ) }
					</th>
					<th scope="col">
						{ __( 'Completed', 'ld-advanced-regrader' ) }
					</th>
					<th scope="col">
						{ __( 'Current score', 'ld-advanced-regrader' ) }
					</th>
					<th scope="col">
						{ __( 'New score', 'ld-advanced-regrader' ) }
					</th>
					<th scope="col">
						{ __( 'Difference', 'ld-advanced-regrader' ) }
					</th>
					<th scope="col">
						{ __( 'Result', 'ld-advanced-regrader' ) }
					</th>
				</tr>
			</thead>
			<tbody>
				{ items.map( ( item ) => {
					const difference = formatDifference( item.difference );
					const flips = item.current_pass !== item.new_pass;

					return (
						<tr key={ item.activity_id }>
							<th scope="row" className="check-column">
								<input
									type="checkbox"
									checked={ selected.has( item.activity_id ) }
									onChange={ () =>
										onToggle( item.activity_id )
									}
									aria-label={ sprintf(
										/* translators: %s: student name. */
										__(
											'Select attempt by %s',
											'ld-advanced-regrader'
										),
										item.display_name
									) }
								/>
							</th>
							<td>
								<strong>{ item.display_name }</strong>
								{ item.regraded && (
									<span className="ldar-tag">
										{ __(
											'Regraded',
											'ld-advanced-regrader'
										) }
									</span>
								) }
							</td>
							<td>
								{ dateI18n(
									dateFormat,
									item.completed * 1000
								) }
							</td>
							<td>
								{ item.current_score }{ ' ' }
								<span className="ldar-muted">
									(
									{ formatPercent( item.current_percentage ) }
									)
								</span>
							</td>
							<td>
								<strong>{ item.new_score }</strong>
								{ ' / ' }
								{ item.new_total }{ ' ' }
								<span className="ldar-muted">
									({ formatPercent( item.new_percentage ) })
								</span>
								{ item.kept_questions > 0 && (
									<span className="ldar-note">
										{ sprintf(
											/* translators: %d: number of questions. */
											_n(
												'%d question kept as originally scored',
												'%d questions kept as originally scored',
												item.kept_questions,
												'ld-advanced-regrader'
											),
											item.kept_questions
										) }
									</span>
								) }
							</td>
							<td>
								<span
									className={ `ldar-diff ldar-diff--${ difference.tone }` }
								>
									{ difference.label }
								</span>
							</td>
							<td>
								{ flips ? (
									<span
										className={ `ldar-flip ldar-flip--${
											item.new_pass ? 'up' : 'down'
										}` }
									>
										{ passLabel( item.current_pass ) }
										{ ' → ' }
										{ passLabel( item.new_pass ) }
									</span>
								) : (
									passLabel( item.new_pass )
								) }
							</td>
						</tr>
					);
				} ) }
			</tbody>
		</table>
	);
}
