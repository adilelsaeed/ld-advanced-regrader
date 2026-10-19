/**
 * Confirmation dialog for actions that write scores.
 */
import { __ } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';

/**
 * @param {Object}   props              Props.
 * @param {string}   props.title        Dialog title.
 * @param {Element}  props.children     Body.
 * @param {string}   props.confirmLabel Confirm button label.
 * @param {Function} props.onConfirm    Called on confirm.
 * @param {Function} props.onCancel     Called on cancel or close.
 * @return {Element} Modal.
 */
export default function ConfirmModal( {
	title,
	children,
	confirmLabel,
	onConfirm,
	onCancel,
} ) {
	return (
		<Modal title={ title } onRequestClose={ onCancel } size="medium">
			<div className="ldar-modal-body">{ children }</div>
			<div className="ldar-modal-actions">
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'ld-advanced-regrader' ) }
				</Button>
				<Button variant="primary" onClick={ onConfirm }>
					{ confirmLabel }
				</Button>
			</div>
		</Modal>
	);
}
