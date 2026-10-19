/**
 * Run batch requests one after another until the server reports it is done.
 *
 * Requests are sequential on purpose: each one is a short PHP request, so
 * large quizzes never hit PHP time or memory limits.
 *
 * @param {Function} requestBatch         Called with the offset; resolves to
 *                                        `{ processed, updated, total, next_offset, done }`.
 * @param {Object}   [options]            Options.
 * @param {Function} [options.onProgress] Called after each batch with `{ processed, total, updated }`.
 * @param {Function} [options.shouldStop] Return true to stop before the next batch.
 * @return {Promise<{processed: number, total: number, updated: number, stopped: boolean}>} Summary.
 */
export async function runBatches(
	requestBatch,
	{ onProgress = () => {}, shouldStop = () => false } = {}
) {
	let processed = 0;
	let updated = 0;
	let total = 0;
	let done = false;

	do {
		if ( shouldStop() ) {
			return { processed, total, updated, stopped: true };
		}

		const result = await requestBatch( processed );

		total = result.total;
		updated += result.updated;
		processed = result.next_offset;
		// Guard against an endless loop if a batch makes no progress.
		done = result.done || result.processed === 0;

		onProgress( {
			processed: Math.min( processed, total ),
			total,
			updated,
		} );
	} while ( ! done );

	return {
		processed: Math.min( processed, total ),
		total,
		updated,
		stopped: false,
	};
}
