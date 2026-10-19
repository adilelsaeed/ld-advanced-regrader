/**
 * Display helpers.
 */

/**
 * Label and tone for a score difference.
 *
 * @param {number} difference New score minus current score.
 * @return {{label: string, tone: 'positive'|'negative'|'neutral'}} Formatted difference.
 */
export function formatDifference( difference ) {
	const value = Number( difference ) || 0;

	if ( value > 0 ) {
		return { label: `+${ value }`, tone: 'positive' };
	}

	if ( value < 0 ) {
		return { label: String( value ), tone: 'negative' };
	}

	return { label: '0', tone: 'neutral' };
}

/**
 * Percentage with at most two decimals and no trailing zeros.
 *
 * @param {number} value Percentage.
 * @return {string} Formatted percentage, e.g. "68.18%".
 */
export function formatPercent( value ) {
	const number = Number( value ) || 0;
	return `${ Number( number.toFixed( 2 ) ) }%`;
}

/**
 * Count how many rows on a page would change.
 *
 * @param {Array<{difference: number, current_pass: boolean, new_pass: boolean}>} items Rows.
 * @return {{changed: number, up: number, down: number, passFlips: number}} Summary.
 */
export function summarize( items ) {
	return items.reduce(
		( summary, item ) => {
			if ( item.difference > 0 ) {
				summary.up++;
			} else if ( item.difference < 0 ) {
				summary.down++;
			}
			if ( item.difference !== 0 ) {
				summary.changed++;
			}
			if ( Boolean( item.current_pass ) !== Boolean( item.new_pass ) ) {
				summary.passFlips++;
			}
			return summary;
		},
		{ changed: 0, up: 0, down: 0, passFlips: 0 }
	);
}
