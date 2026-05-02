/**
 * External Dependencies
 */
import { WPEntitySearch } from '@prc/components';
import { useTaxonomy } from '@prc/hooks';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, TextControl } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import Step from './_step';
import { TAXONOMY, TAXONOMY_LABEL } from '../../constants';

/**
 * Search for, select, or create a new block area.
 *
 * @param {Object}   props
 * @param {string}   props.blockAreaSlug
 * @param {Function} props.setBlockAreaSlug
 * @param {string}   props.wizardNewBlockAreaName
 * @param {boolean}  props.wizardIsCreatingNewBlockArea
 * @param {Function} props.setAttributes
 */
export default function QueryA({
	blockAreaSlug,
	setBlockAreaSlug,
	wizardNewBlockAreaName,
	wizardIsCreatingNewBlockArea,
	setAttributes,
}) {
	const [blockAreaId, blockAreaName] = useTaxonomy(TAXONOMY, blockAreaSlug);

	const createNewPrompt = (
		<span>
			No Block Area could be found with that name, please create a new
			one.{' '}
			<Button
				variant="link"
				onClick={() =>
					setAttributes({
						wizardIsCreatingNewBlockArea: true,
						blockAreaSlug: null,
						blockAreaQueryComplete: false,
					})
				}
			>
				{__('Create New Block Area', 'prc-platform-core')}
			</Button>
		</span>
	);

	return (
		<Step>
			{!wizardIsCreatingNewBlockArea && (
				<WPEntitySearch
					placeholder={__(
						'Search for an existing block area, or create a new one',
						'prc-platform-core'
					)}
					searchLabel={`Search for ${TAXONOMY_LABEL}`}
					entityType="taxonomy"
					entitySubType={TAXONOMY}
					entityId={blockAreaId || false}
					searchValue={blockAreaName || ''}
					onSelect={(entity) => {
						const slug = entity?.entitySlug ?? entity?.slug;
						console.log('query a WPEntitySearch entity: ', entity);
						if (slug) {
							setBlockAreaSlug(slug);
						}
					}}
					onKeyEnter={() => {}}
					onKeyESC={() => {}}
					perPage={10}
					showExcerpt={true}
					createNew={createNewPrompt}
				/>
			)}

			{wizardIsCreatingNewBlockArea && (
				<TextControl
					label={__('New Block Area Name', 'prc-platform-core')}
					value={wizardNewBlockAreaName}
					onChange={(value) =>
						setAttributes({ wizardNewBlockAreaName: value })
					}
				/>
			)}
		</Step>
	);
}
