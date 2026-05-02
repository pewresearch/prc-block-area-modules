/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { Flex, PanelBody } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import BlockAreaControl from './block-area';
import BlockModuleControl from './block-module';
import TaxonomyControl from './taxonomy';

export default function Controls({
	attributes,
	setAttributes,
	blockArea,
	taxonomy,
	blockModule,
	postStatus,
	setPostStatus,
}) {
	const { ref } = attributes;
	const showBlockAreaPanel = Boolean(ref || blockArea?.id);

	return (
		<InspectorControls>
			{showBlockAreaPanel && (
				<PanelBody title={__('Block Area')} initialOpen={true}>
					<Flex direction="column" gap="10px">
						<BlockAreaControl
							attributes={attributes}
							setAttributes={setAttributes}
							blockArea={blockArea}
							postStatus={postStatus}
							setPostStatus={setPostStatus}
						/>
					</Flex>
				</PanelBody>
			)}
			{!ref && (
				<PanelBody title={__('Taxonomy')} initialOpen={true}>
					<Flex direction="column" gap="10px">
						<TaxonomyControl
							attributes={attributes}
							setAttributes={setAttributes}
							taxonomy={taxonomy}
						/>
					</Flex>
				</PanelBody>
			)}
			<PanelBody title={__('Block Module')} initialOpen={true}>
				<Flex direction="column" gap="10px">
					<BlockModuleControl
						{...{
							attributes,
							setAttributes,
							blockArea,
							taxonomy,
							blockModule,
						}}
					/>
				</Flex>
			</PanelBody>
		</InspectorControls>
	);
}
