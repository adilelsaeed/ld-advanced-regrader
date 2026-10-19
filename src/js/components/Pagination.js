/**
 * Previous / next pagination.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * @param {Object}   props            Props.
 * @param {number}   props.page       Current page.
 * @param {number}   props.totalPages Total pages.
 * @param {Function} props.onChange   Called with the new page.
 * @param {boolean}  props.disabled   Disable navigation.
 * @return {Element|null} Pagination.
 */
export default function Pagination( { page, totalPages, onChange, disabled } ) {
	if ( totalPages <= 1 ) {
		return null;
	}

	return (
		<nav
			className="ldar-pagination"
			aria-label={ __( 'Attempts pages', 'ld-advanced-regrader' ) }
		>
			<Button
				variant="secondary"
				size="compact"
				disabled={ disabled || page <= 1 }
				onClick={ () => onChange( page - 1 ) }
			>
				{ __( 'Previous', 'ld-advanced-regrader' ) }
			</Button>
			<span>
				{ sprintf(
					/* translators: 1: current page, 2: total pages. */
					__( 'Page %1$d of %2$d', 'ld-advanced-regrader' ),
					page,
					totalPages
				) }
			</span>
			<Button
				variant="secondary"
				size="compact"
				disabled={ disabled || page >= totalPages }
				onClick={ () => onChange( page + 1 ) }
			>
				{ __( 'Next', 'ld-advanced-regrader' ) }
			</Button>
		</nav>
	);
}
