/**
 * How a block area chooses its term filter.
 */
export const TERM_SOURCE = {
	NONE: 'none',
	FIXED: 'fixed',
	TEMPLATE: 'template',
};

/**
 * @param {Object} attributes Block attributes.
 * @return {'none'|'fixed'|'template'} The term source.
 */
export function getTermSource(attributes) {
	if (attributes?.inheritTermFromTemplate) {
		return TERM_SOURCE.TEMPLATE;
	}
	if (attributes?.taxonomyTermSlug) {
		return TERM_SOURCE.FIXED;
	}
	return TERM_SOURCE.NONE;
}

/**
 * @param {'none'|'fixed'|'template'} source
 * @param {Object}                    extras
 * @param {string}                    [extras.taxonomyName]
 * @param {string}                    [extras.taxonomyTermSlug]
 * @return {Object} Attribute patch for the given source.
 */
export function termSourcePatch(source, extras = {}) {
	if (source === TERM_SOURCE.TEMPLATE) {
		return {
			inheritTermFromTemplate: true,
			taxonomyName: extras.taxonomyName,
			taxonomyTermSlug: undefined,
		};
	}
	if (source === TERM_SOURCE.FIXED) {
		return {
			inheritTermFromTemplate: false,
			taxonomyName: extras.taxonomyName,
			taxonomyTermSlug: extras.taxonomyTermSlug,
		};
	}
	return {
		inheritTermFromTemplate: false,
		taxonomyTermSlug: undefined,
	};
}

/**
 * Parse a WordPress taxonomy template slug.
 *
 * @param {string}        templateSlug
 * @param {Array<string>} taxonomySlugs
 * @return {{ taxonomy: string, termSlug: string|null }|null} Taxonomy and term, or null.
 */
export function parseTaxonomyTemplate(templateSlug, taxonomySlugs = []) {
	if (!templateSlug) {
		return null;
	}

	if (templateSlug === 'category') {
		return { taxonomy: 'category', termSlug: null };
	}
	if (templateSlug.startsWith('category-')) {
		return {
			taxonomy: 'category',
			termSlug: templateSlug.slice('category-'.length),
		};
	}
	if (templateSlug === 'tag') {
		return { taxonomy: 'post_tag', termSlug: null };
	}
	if (templateSlug.startsWith('tag-')) {
		return {
			taxonomy: 'post_tag',
			termSlug: templateSlug.slice('tag-'.length),
		};
	}

	const prefix = 'taxonomy-';
	if (!templateSlug.startsWith(prefix)) {
		return null;
	}

	const remainder = templateSlug.slice(prefix.length);
	if (!remainder) {
		return null;
	}

	const sorted = [...taxonomySlugs]
		.filter(Boolean)
		.sort((a, b) => b.length - a.length);

	for (const slug of sorted) {
		if (remainder === slug) {
			return { taxonomy: slug, termSlug: null };
		}
		if (remainder.startsWith(`${slug}-`)) {
			const termSlug = remainder.slice(slug.length + 1);
			return { taxonomy: slug, termSlug: termSlug || null };
		}
	}

	return null;
}
