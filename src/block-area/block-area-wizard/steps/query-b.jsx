/**
 * External Dependencies
 */
import { WPEntitySearch } from '@prc/components';
import { useTaxonomy } from '@prc/hooks';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	ToggleControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';

/**
 * Internal Dependencies
 */
import { TAXONOMY_LABEL } from '../../constants';

/**
 * Search for and select a taxonomy, or inherit from the template.
 *
 * @param {Object}   props
 * @param {string}   props.taxonomyName
 * @param {string}   props.taxonomyTermSlug
 * @param {Function} props.setTaxonomyTermSlug
 * @param {string}   props.templateSlug
 * @param {boolean}  props.allowTaxonomySelection
 * @param {boolean}  props.inheritTermFromTemplate
 * @param {Function} props.setInheritTermFromTemplate
 * @param {Function} props.setAttributes
 * @param {boolean}  props.isTaxonomyTemplate
 * @return {import('react').JSX.Element} The component.
 */
export default function QueryB({
	taxonomyName,
	taxonomyTermSlug,
	setTaxonomyTermSlug,
	templateSlug,
	allowTaxonomySelection,
	inheritTermFromTemplate,
	setInheritTermFromTemplate,
	setAttributes,
	isTaxonomyTemplate,
}) {
	const templateSlugCleaned = templateSlug?.replace(`${taxonomyName}-`, '');

	const [templateTermId, templateTermName] = useTaxonomy(
		taxonomyName,
		templateSlugCleaned
	);
	const [termId, termName] = useTaxonomy(taxonomyName, taxonomyTermSlug);

	const toggleAllowTaxonomySelection = () => {
		setAttributes({
			wizardAllowTaxonomySelection: !allowTaxonomySelection,
		});
	};

	return (
		<div>
			<VStack spacing="2">
				<BaseControl
					label={__('Query by Taxonomy?', 'prc-platform-core')}
					id="query-by-taxonomy-boolean"
				>
					<ToggleControl
						label={__('Query by Taxonomy')}
						checked={allowTaxonomySelection}
						onChange={toggleAllowTaxonomySelection}
					/>
				</BaseControl>
				{allowTaxonomySelection && (
					<>
						{isTaxonomyTemplate && (
							<BaseControl
								label={__(
									'Inherit Taxonomy Term from Template?',
									'prc-platform-core'
								)}
								id="inherit-taxonomy-term-boolean"
							>
								<ToggleControl
									label={
										inheritTermFromTemplate
											? __('Yes', 'prc-platform-core')
											: __('No', 'prc-platform-core')
									}
									checked={inheritTermFromTemplate}
									onChange={() =>
										setInheritTermFromTemplate(
											!inheritTermFromTemplate
										)
									}
								/>
							</BaseControl>
						)}
						{true !== inheritTermFromTemplate && (
							<WPEntitySearch
								placeholder={`Search for a taxonomy term to filter ${TAXONOMY_LABEL} by`}
								searchLabel={`Search for a taxonomy term to filter ${TAXONOMY_LABEL} by`}
								entityType="taxonomy"
								entitySubType={taxonomyName}
								entityId={templateTermId || termId || false}
								searchValue={templateTermName || termName || ''}
								onSelect={(entity) => {
									if (entity?.entitySlug) {
										setTaxonomyTermSlug(entity.entitySlug);
									}
								}}
								onKeyEnter={() => {}}
								onKeyESC={() => {}}
								perPage={10}
							/>
						)}
					</>
				)}
			</VStack>
		</div>
	);
}
