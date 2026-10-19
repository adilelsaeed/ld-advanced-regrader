/**
 * External dependencies
 */
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import App from '../App';
import * as api from '../api';

jest.mock( '../api' );

// The real filters use ComboboxControl; a plain stand-in keeps these tests
// about the app's data flow rather than the component library.
jest.mock( '../components/Filters', () => ( { value, onChange, onSubmit } ) => (
	<form
		onSubmit={ ( event ) => {
			event.preventDefault();
			onSubmit();
		} }
	>
		<input
			aria-label="quiz"
			value={ value.quizId }
			onChange={ ( event ) =>
				onChange( { ...value, quizId: event.target.value } )
			}
		/>
		<button type="submit">preview</button>
	</form>
) );

const attempt = ( id, overrides = {} ) => ( {
	activity_id: id,
	user_id: id * 10,
	display_name: `Student ${ id }`,
	completed: 1760000000,
	current_score: 5,
	current_percentage: 50,
	current_pass: false,
	new_score: 8,
	new_total: 10,
	new_percentage: 80,
	new_pass: true,
	difference: 3,
	kept_questions: 0,
	regraded: false,
	...overrides,
} );

const previewQuiz = async () => {
	fireEvent.change( screen.getByLabelText( 'quiz' ), {
		target: { value: '42' },
	} );
	fireEvent.click( screen.getByText( 'preview' ) );
	await screen.findByText( 'Student 1' );
};

beforeEach( () => {
	jest.resetAllMocks();
	api.fetchOptions.mockResolvedValue( { quizzes: [], groups: [] } );
	api.fetchAttempts.mockResolvedValue( {
		items: [
			attempt( 1 ),
			attempt( 2, {
				difference: 0,
				new_score: 5,
				new_percentage: 50,
				new_pass: false,
			} ),
		],
		total: 2,
	} );
} );

describe( 'App', () => {
	it( 'loads the preview for the chosen quiz', async () => {
		render( <App settings={ { perPage: 20 } } /> );
		await screen.findByText( 'preview' );

		await previewQuiz();

		expect( api.fetchAttempts ).toHaveBeenCalledWith( {
			quizId: 42,
			groupId: 0,
			search: '',
			page: 1,
			perPage: 20,
		} );
		expect( screen.getByText( '+3' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Fail → Pass' ) ).toBeInTheDocument();
	} );

	it( 'sends only attempt IDs when saving the selection', async () => {
		api.regradeSelected.mockResolvedValue( { requested: 1, updated: 1 } );
		render( <App settings={ { perPage: 20 } } /> );
		await screen.findByText( 'preview' );
		await previewQuiz();

		fireEvent.click(
			screen.getByLabelText( 'Select attempt by Student 1' )
		);
		fireEvent.click(
			screen.getByRole( 'button', {
				name: 'Save new scores for selected (1)',
			} )
		);
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Save new scores' } )
		);

		await waitFor( () =>
			expect( api.regradeSelected ).toHaveBeenCalledWith( {
				quizId: 42,
				activityIds: [ 1 ],
				updateCourse: false,
			} )
		);
		// Notice also announces its text to screen readers, so it appears twice.
		const notices = await screen.findAllByText( 'Updated 1 attempt.' );
		expect( notices[ 0 ] ).toBeInTheDocument();
	} );

	it( 'does not save anything when the confirmation is cancelled', async () => {
		render( <App settings={ { perPage: 20 } } /> );
		await screen.findByText( 'preview' );
		await previewQuiz();

		fireEvent.click(
			screen.getByRole( 'button', {
				name: 'Save new scores for all 2 matching',
			} )
		);
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Cancel' } )
		);

		expect( api.regradeBatch ).not.toHaveBeenCalled();
	} );

	it( 'shows an error notice when loading fails', async () => {
		api.fetchOptions.mockRejectedValue( new Error( 'Forbidden' ) );

		render( <App settings={ {} } /> );

		// Notice also announces its text to screen readers, so it appears twice.
		const messages = await screen.findAllByText( 'Forbidden' );
		expect( messages[ 0 ] ).toBeInTheDocument();
	} );
} );
