/**
 * Internal Dependencies
 */
import Step from './_step';

/**
 * Confirm Block Area and other taxonomy options are correct
 *
 * @param {Object}  props
 * @param {string}  props.blockAreaSlug
 * @param {string}  props.taxonomyName
 * @param {string}  props.taxonomyTermSlug
 * @param {boolean} props.inheritTermFromTemplate
 * @param {string}  props.newBlockAreaName
 */
export default function QueryC({
	blockAreaSlug,
	taxonomyName,
	taxonomyTermSlug,
	inheritTermFromTemplate,
	newBlockAreaName,
}) {
	return (
		<Step>
			<h5 className="block-area-edit__review-settings-heading">
				Review Block Area Settings:
			</h5>
			{!newBlockAreaName && (
				<p>
					This area will render the latest public{' '}
					<pre>block_module</pre> that is in the{' '}
					<pre>{blockAreaSlug}</pre> block area
					{taxonomyTermSlug && (
						<span>
							{' '}
							and <pre>{taxonomyTermSlug}</pre> {taxonomyName}
						</span>
					)}
					{true === inheritTermFromTemplate && (
						<span>
							{' '}
							and will <pre>inherit the {taxonomyName}</pre> from
							available context
						</span>
					)}
					.
				</p>
			)}
			{newBlockAreaName && (
				<p>
					This area will render the latest public{' '}
					<pre>block_module</pre> that is in the new{' '}
					<pre>{newBlockAreaName}</pre> block area
					{taxonomyTermSlug && (
						<span>
							{' '}
							and <pre>{taxonomyTermSlug}</pre> {taxonomyName}
						</span>
					)}
					.
				</p>
			)}
		</Step>
	);
}
