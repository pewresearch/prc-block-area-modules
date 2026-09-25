/**
 * WordPress Dependencies
 */
import { Fragment } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { TextControl, FlexBlock } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal Dependencies
 */
import { POST_TYPE, POST_TYPE_LABEL } from '../constants';
import BlockModuleCreate from '../block-module-create';

export default function BlockModuleControl({
	setAttributes,
	blockArea,
	taxonomy,
	blockModule,
	restBase,
}) {
	const { id } = blockModule;

	const blockAreaId = blockArea?.id;
	const taxonomyId = taxonomy?.id;

	// Block Module:
	const [blockModuleTitle, setBlockModuleTitle] = useEntityProp(
		'postType',
		POST_TYPE,
		'title',
		id
	);

	if (!id) {
		return null;
	}

	return (
		<Fragment>
			<FlexBlock>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={`${POST_TYPE_LABEL} Title`}
					value={decodeEntities(blockModuleTitle)}
					onChange={setBlockModuleTitle}
				/>
			</FlexBlock>
			<FlexBlock>
				<BlockModuleCreate
					{...{
						blockAreaId,
						taxonomyTermId: taxonomyId,
						restBase,
						setAttributes,
					}}
				/>
			</FlexBlock>
		</Fragment>
	);
}
