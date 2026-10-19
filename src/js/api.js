/**
 * REST client for the regrader endpoints.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

export const NAMESPACE = '/ld-advanced-regrader/v1';

/**
 * Quizzes and groups for the filters.
 *
 * @return {Promise<{quizzes: Array<{id: number, title: string}>, groups: Array<{id: number, title: string}>}>} Options.
 */
export function fetchOptions() {
	return apiFetch( { path: `${ NAMESPACE }/options` } );
}

/**
 * One page of attempts with current and recalculated scores.
 *
 * @param {Object} query         Query.
 * @param {number} query.quizId  Quiz ID.
 * @param {number} query.groupId Group ID, 0 for all.
 * @param {string} query.search  Search string.
 * @param {number} query.page    Page number.
 * @param {number} query.perPage Rows per page.
 * @return {Promise<{items: Object[], total: number}>} Attempts.
 */
export function fetchAttempts( { quizId, groupId, search, page, perPage } ) {
	return apiFetch( {
		path: addQueryArgs( `${ NAMESPACE }/attempts`, {
			quiz_id: quizId,
			group_id: groupId,
			search,
			page,
			per_page: perPage,
		} ),
	} );
}

/**
 * Recalculate and save the selected attempts.
 *
 * Only IDs are sent; the server recalculates every score itself.
 *
 * @param {Object}   args              Arguments.
 * @param {number}   args.quizId       Quiz ID.
 * @param {number[]} args.activityIds  Attempts to update.
 * @param {boolean}  args.updateCourse Mark the quiz complete when it now passes.
 * @return {Promise<{requested: number, updated: number}>} Result.
 */
export function regradeSelected( { quizId, activityIds, updateCourse } ) {
	return apiFetch( {
		path: `${ NAMESPACE }/regrade`,
		method: 'POST',
		data: {
			quiz_id: quizId,
			activity_ids: activityIds,
			update_course: updateCourse,
		},
	} );
}

/**
 * Recalculate and save the next batch of attempts matching the filters.
 *
 * @param {Object}  args              Arguments.
 * @param {number}  args.quizId       Quiz ID.
 * @param {number}  args.groupId      Group ID, 0 for all.
 * @param {string}  args.search       Search string.
 * @param {number}  args.offset       Rows already processed.
 * @param {boolean} args.updateCourse Mark the quiz complete when it now passes.
 * @return {Promise<{processed: number, updated: number, total: number, next_offset: number, done: boolean}>} Batch result.
 */
export function regradeBatch( {
	quizId,
	groupId,
	search,
	offset,
	updateCourse,
} ) {
	return apiFetch( {
		path: `${ NAMESPACE }/regrade/batch`,
		method: 'POST',
		data: {
			quiz_id: quizId,
			group_id: groupId,
			search,
			offset,
			update_course: updateCourse,
		},
	} );
}
