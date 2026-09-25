/**
 * WordPress Dependencies
 */
import { store as coreStore } from '@wordpress/core-data';
import { dispatch } from '@wordpress/data';

/**
 * Internal Dependencies
 */
import { POST_TYPE, TAXONOMY_REST_BASE } from '../constants';

export default async function createBlockModule(
	blockModuleTitle,
	blockAreaId,
	restBase,
	taxonomyTermId,
	status = 'publish'
) {
	const args = {
		title: blockModuleTitle,
		status,
	};
	if (blockAreaId) {
		args[TAXONOMY_REST_BASE] = [blockAreaId];
	}
	if (taxonomyTermId && restBase) {
		args[restBase] = [taxonomyTermId];
	}

	const { saveEntityRecord } = dispatch(coreStore);

	const newBlockModule = await saveEntityRecord('postType', POST_TYPE, args);

	if (newBlockModule) {
		return newBlockModule;
	}

	return false;
}
