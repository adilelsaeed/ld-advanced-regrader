/**
 * Quiz, group and student filters.
 */
import { __ } from '@wordpress/i18n';
import { Button, ComboboxControl, TextControl } from '@wordpress/components';

/**
 * @param {Object}   props           Props.
 * @param {Object}   props.options   `{ quizzes, groups }` from the API.
 * @param {Object}   props.value     `{ quizId, groupId, search }`.
 * @param {Function} props.onChange  Called with the next value.
 * @param {Function} props.onSubmit  Called when the form is submitted.
 * @param {boolean}  props.isLoading Whether results are loading.
 * @return {Element} Filters form.
 */
export default function Filters( {
	options,
	value,
	onChange,
	onSubmit,
	isLoading,
} ) {
	const quizOptions = options.quizzes.map( ( quiz ) => ( {
		value: String( quiz.id ),
		label: quiz.title,
	} ) );

	const groupOptions = [
		{ value: '0', label: __( 'All groups', 'ld-advanced-regrader' ) },
		...options.groups.map( ( group ) => ( {
			value: String( group.id ),
			label: group.title,
		} ) ),
	];

	const update = ( key ) => ( next ) =>
		onChange( { ...value, [ key ]: next ?? '' } );

	return (
		<form
			className="ldar-filters"
			onSubmit={ ( event ) => {
				event.preventDefault();
				onSubmit();
			} }
		>
			<ComboboxControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Quiz', 'ld-advanced-regrader' ) }
				value={ value.quizId }
				options={ quizOptions }
				onChange={ update( 'quizId' ) }
			/>
			<ComboboxControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Group', 'ld-advanced-regrader' ) }
				value={ value.groupId }
				options={ groupOptions }
				onChange={ ( next ) =>
					onChange( { ...value, groupId: next || '0' } )
				}
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Student', 'ld-advanced-regrader' ) }
				placeholder={ __(
					'Name, email or username',
					'ld-advanced-regrader'
				) }
				value={ value.search }
				onChange={ update( 'search' ) }
			/>
			<Button
				__next40pxDefaultSize
				variant="primary"
				type="submit"
				disabled={ ! value.quizId || isLoading }
				isBusy={ isLoading }
			>
				{ __( 'Preview new scores', 'ld-advanced-regrader' ) }
			</Button>
		</form>
	);
}
