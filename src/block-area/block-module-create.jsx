/**
 * External Dependencies
 */
import { EntityCreateNewModal } from '@prc/components';

/**
 * WordPress Dependencies
 */
import { useState, Fragment } from '@wordpress/element';
import { Button } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import { createBlockModule } from './functions';

export default function BlockModuleCreate({
	blockAreaId,
	taxonomyTermId,
	restBase,
	setAttributes,
}) {
	const [displayModal, setDisplayModal] = useState(false);

	return (
		<Fragment>
			<Button
				__next40pxDefaultSize
				variant="secondary"
				onClick={() => {
					setDisplayModal(!displayModal);
				}}
			>
				Create New Block Module
			</Button>
			{displayModal && (
				<EntityCreateNewModal
					{...{
						defaultTitle: 'Block Module',
						onClose: () => {
							setDisplayModal(false);
						},
						onSubmit: (newTitle) => {
							createBlockModule(
								newTitle,
								blockAreaId,
								restBase,
								taxonomyTermId,
								'publish'
							).then((response) => {
								setAttributes({ ref: response.id });
							});
						},
					}}
				/>
			)}
		</Fragment>
	);
}
