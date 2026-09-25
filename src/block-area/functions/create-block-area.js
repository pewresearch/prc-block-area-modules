/**
 * WordPress Dependencies
 */
import { store as coreStore } from '@wordpress/core-data';
import { dispatch } from '@wordpress/data';
import { cleanForSlug } from '@wordpress/url';

/**
 * Internal Dependencies
 */
import { TAXONOMY } from '../constants';

export default async function createBlockArea(blockAreaName) {
	const { saveEntityRecord } = dispatch(coreStore);
	const slug = cleanForSlug(blockAreaName);

	const newBlockArea = await saveEntityRecord('taxonomy', TAXONOMY, {
		name: blockAreaName,
		slug,
	});
	if (newBlockArea) {
		return newBlockArea?.slug;
	}

	return false;
}
