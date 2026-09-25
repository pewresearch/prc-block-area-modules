/**
 * External Dependencies
 */
import { WPEntitySearch } from '@prc/components';
import { useTaxonomy } from '@prc/hooks';

/**
 * WordPress Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { RadioControl, SelectControl } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import {
	TERM_SOURCE,
	getTermSource,
	termSourcePatch,
	parseTaxonomyTemplate,
} from './term-source';
import { useBlockModuleTaxonomies } from './hooks';

/**
 * Shared term-source picker for the wizard and the inspector.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes
 * @param {Function} props.setAttributes
 * @param {string}   props.templateSlug
 * @param {string}   [props.source]
 * @param {Function} [props.onSourceChange]
 */
export default function TermSourceControl({
	attributes,
	setAttributes,
	templateSlug,
	source: sourceOverride,
	onSourceChange,
}) {
	const { taxonomyName, taxonomyTermSlug } = attributes;
	const { taxonomies } = useBlockModuleTaxonomies();
	const taxonomySlugs = taxonomies.map((taxonomy) => taxonomy.slug);
	const parsed = parseTaxonomyTemplate(templateSlug, taxonomySlugs);
	const [isPickingTerm, setIsPickingTerm] = useState(false);
	const inferredSource = getTermSource(attributes);
	const source =
		sourceOverride ??
		(isPickingTerm && inferredSource === TERM_SOURCE.NONE
			? TERM_SOURCE.FIXED
			: inferredSource);
	const [termId, termName] = useTaxonomy(taxonomyName, taxonomyTermSlug);

	const templateTaxonomyLabel =
		taxonomies.find((taxonomy) => taxonomy.slug === parsed?.taxonomy)
			?.name || parsed?.taxonomy;

	const options = [
		{
			label: __("Don't filter by taxonomy", 'prc-platform-core'),
			value: TERM_SOURCE.NONE,
		},
		{
			label: __('A specific term', 'prc-platform-core'),
			value: TERM_SOURCE.FIXED,
		},
	];
	if (parsed) {
		options.push({
			label: sprintf(
				/* translators: %s: taxonomy label from the current template */
				__(
					'Inherit the term from this template (%s)',
					'prc-platform-core'
				),
				templateTaxonomyLabel
			),
			value: TERM_SOURCE.TEMPLATE,
		});
	}

	const handleSourceChange = (next) => {
		if (onSourceChange) {
			onSourceChange(next);
			return;
		}
		setIsPickingTerm(next === TERM_SOURCE.FIXED);
		if (next === TERM_SOURCE.TEMPLATE) {
			setAttributes(
				termSourcePatch(TERM_SOURCE.TEMPLATE, {
					taxonomyName: parsed?.taxonomy,
				})
			);
			return;
		}
		if (next === TERM_SOURCE.FIXED) {
			setAttributes(
				termSourcePatch(TERM_SOURCE.FIXED, {
					taxonomyName,
					taxonomyTermSlug,
				})
			);
			return;
		}
		setAttributes(termSourcePatch(TERM_SOURCE.NONE));
	};

	return (
		<>
			<RadioControl
				selected={source}
				options={options}
				onChange={handleSourceChange}
				help={
					source === TERM_SOURCE.TEMPLATE
						? __(
								"On each archive page, shows the latest module tagged with that page's term.",
								'prc-platform-core'
							)
						: undefined
				}
			/>
			{source === TERM_SOURCE.FIXED && (
				<>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={__('Taxonomy', 'prc-platform-core')}
						value={taxonomyName}
						options={taxonomies.map((taxonomy) => ({
							label: taxonomy.name,
							value: taxonomy.slug,
						}))}
						onChange={(value) => {
							setAttributes({
								taxonomyName: value,
								taxonomyTermSlug: undefined,
							});
						}}
					/>
					{taxonomyName && (
						<WPEntitySearch
							placeholder={__(
								'Search for a term',
								'prc-platform-core'
							)}
							searchLabel={__(
								'Search for a term',
								'prc-platform-core'
							)}
							entityType="taxonomy"
							entitySubType={taxonomyName}
							entityId={termId || false}
							searchValue={termName || ''}
							onSelect={(entity) => {
								const slug = entity?.entitySlug ?? entity?.slug;
								if (slug) {
									setAttributes({ taxonomyTermSlug: slug });
								}
							}}
							onKeyEnter={() => {}}
							onKeyESC={() => {}}
							perPage={10}
						/>
					)}
				</>
			)}
		</>
	);
}
