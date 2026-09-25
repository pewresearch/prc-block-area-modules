/**
 * WordPress Dependencies
 */
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

const QUERY = {
	type: 'block_module',
	per_page: -1,
	context: 'view',
};

/**
 * Taxonomies registered on block_module, excluding block_area.
 *
 * @return {{ taxonomies: Array<{ slug: string, name: string, restBase: string }>, isResolving: boolean }} Taxonomies and resolve state.
 */
export default function useBlockModuleTaxonomies() {
	const { records, isResolving } = useSelect((select) => {
		const { getTaxonomies, isResolving: getIsResolving } =
			select(coreStore);
		return {
			records: getTaxonomies(QUERY),
			isResolving: getIsResolving('getTaxonomies', [QUERY]),
		};
	}, []);

	const taxonomies = (records || [])
		.filter((taxonomy) => taxonomy.slug !== 'block_area')
		.map((taxonomy) => ({
			slug: taxonomy.slug,
			name: taxonomy.name,
			restBase: taxonomy.rest_base,
		}));

	return { taxonomies, isResolving };
}
