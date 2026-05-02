/**
 * External Dependencies
 */
import { EntityPatternModal } from '@prc/components';
import styled from '@emotion/styled';

/**
 * WordPress Dependencies
 */
import { Fragment, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useEntityProp } from '@wordpress/core-data';
import {
	TextControl,
	Button,
	ToggleControl,
	FlexBlock,
	Notice,
	__experimentalHStack as HStack,
} from '@wordpress/components';

/**
 * Internal Dependencies
 */
import {
	TAXONOMY,
	TAXONOMY_LABEL,
	POST_TYPE,
	POST_TYPE_LABEL,
} from '../constants';

const StyledNotice = styled(Notice)`
	margin-bottom: 1em;
`;

/**
 * @param {Object}   props
 * @param {Object}   props.attributes
 * @param {Function} props.setAttributes
 * @param {Object}   props.blockArea
 * @param {string}   props.postStatus
 * @param {Function} props.setPostStatus
 */
export default function BlockAreaControl({
	attributes,
	setAttributes,
	blockArea,
	postStatus,
	setPostStatus,
}) {
	const { metadata, ref } = attributes;
	const { id } = blockArea;
	const [isPickerOpen, setIsPickerOpen] = useState(false);

	const [blockAreaName, setBlockAreaName] = useEntityProp(
		'taxonomy',
		TAXONOMY,
		'name',
		id
	);

	useEffect(() => {
		if (!metadata?.name && blockAreaName && id) {
			setAttributes({
				metadata: {
					...metadata,
					name: blockAreaName,
				},
			});
		}
	}, [metadata, blockAreaName, id, setAttributes]);

	const handleSelectModule = (item) => {
		if (item?.id) {
			setAttributes({ ref: item.id });
		}
		setIsPickerOpen(false);
	};

	const handleResetPreview = () => {
		setAttributes({ ref: null });
	};

	const handleFullReset = () => {
		setAttributes({
			ref: null,
			blockAreaSlug: null,
			categorySlug: null,
			inheritCategory: null,
		});
	};

	if (!ref && !id) {
		return null;
	}

	return (
		<Fragment>
			{ref && (
				<FlexBlock>
					<StyledNotice status="warning" isDismissible={false}>
						{__(
							'You have pinned a specific block module. This module will always be displayed, even if a newer module is published to this block area. To resume showing the latest module automatically, click "Use dynamic latest module" below.',
							'prc-platform-core'
						)}
					</StyledNotice>
					{id && (
						<TextControl
							label={`${TAXONOMY_LABEL} Name`}
							value={blockAreaName}
							onChange={setBlockAreaName}
						/>
					)}
					<HStack spacing="2" wrap>
						<Button
							variant="secondary"
							onClick={() => setIsPickerOpen(true)}
						>
							{__(
								'Choose a different block module',
								'prc-platform-core'
							)}
						</Button>
						<Button
							variant="secondary"
							onClick={handleResetPreview}
						>
							{__(
								'Use dynamic latest module',
								'prc-platform-core'
							)}
						</Button>
					</HStack>
				</FlexBlock>
			)}

			{!ref && id && (
				<Fragment>
					<FlexBlock>
						<TextControl
							label={`${TAXONOMY_LABEL} Name`}
							value={blockAreaName}
							onChange={setBlockAreaName}
						/>
					</FlexBlock>
					<FlexBlock>
						<ToggleControl
							label={__(
								'Preview Latest Draft Module',
								'prc-platform-core'
							)}
							checked={'draft' === postStatus}
							help={__(
								'This will allow you to preview and edit the latest draft module in the block area. This will not be visible on the front end, the latest published module will always be visible.',
								'prc-platform-core'
							)}
							onChange={(value) => {
								setPostStatus(value ? 'draft' : 'publish');
							}}
						/>
					</FlexBlock>
					<FlexBlock>
						<Button
							variant="secondary"
							onClick={() => setIsPickerOpen(true)}
						>
							{__(
								'Choose specific block module',
								'prc-platform-core'
							)}
						</Button>
					</FlexBlock>
					<FlexBlock>
						<Button
							isDestructive
							variant="secondary"
							onClick={handleFullReset}
						>
							{__('Reset Block Area')}
						</Button>
					</FlexBlock>
				</Fragment>
			)}

			{isPickerOpen && (
				<EntityPatternModal
					title={__('Choose a block module', 'prc-platform-core')}
					instructions={__(
						'Choosing a block module will always display it, overriding any block area or category queries.',
						'prc-platform-core'
					)}
					entityType={POST_TYPE}
					entityTypeLabel={POST_TYPE_LABEL}
					onSelect={handleSelectModule}
					onClose={() => setIsPickerOpen(false)}
					selectedId={ref || null}
				/>
			)}
		</Fragment>
	);
}
