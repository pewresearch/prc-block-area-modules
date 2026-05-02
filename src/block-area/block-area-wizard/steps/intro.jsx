/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Spinner,
	__experimentalHStack as HStack,
} from '@wordpress/components';

import Step from './_step';

/**
 * @param {Object}   props
 * @param {boolean}  props.isResolving
 * @param {Array}    props.blockModules
 * @param {Function} props.setAttributes
 */
export default function Intro({
	isResolving,
	blockModules = [],
	setAttributes,
}) {
	return (
		<Step>
			<p>
				You can configure a block area to dynamically query the latest
				module by the block area and/or an additional taxonomy.
				Alternatively, you can statically insert an existing block
				module or create a new blank one.
			</p>
			{isResolving && <Spinner />}
			{!isResolving && (
				<HStack spacing="3" wrap style={{ maxWidth: 'fit-content' }}>
					<Button
						variant="primary"
						onClick={() => setAttributes({ wizardStep: 'query-a' })}
					>
						{__('Configure Block Area')}
					</Button>
					{!!blockModules.length && (
						<Button
							variant="secondary"
							onClick={() =>
								setAttributes({ wizardStep: 'select-a' })
							}
						>
							{__('Choose Existing Module')}
						</Button>
					)}
					<Button
						variant="secondary"
						onClick={() =>
							setAttributes({ wizardStep: 'create-a' })
						}
					>
						{__('Start New Module')}
					</Button>
				</HStack>
			)}
		</Step>
	);
}
