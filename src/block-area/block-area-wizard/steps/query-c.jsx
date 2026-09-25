/**
 * Internal Dependencies
 */
import Step from './_step';
import { TERM_SOURCE } from '../../term-source';

/**
 * Confirm Block Area and other taxonomy options are correct
 *
 * @param {Object} props
 * @param {string} props.blockAreaSlug
 * @param {string} props.taxonomyName
 * @param {string} props.taxonomyTermSlug
 * @param {string} props.termSource
 * @param {string} props.taxonomyLabel
 * @param {string} props.newBlockAreaName
 */
export default function QueryC({
	blockAreaSlug,
	taxonomyName,
	taxonomyTermSlug,
	termSource,
	taxonomyLabel,
	newBlockAreaName,
}) {
	const templateFilter =
		termSource === TERM_SOURCE.TEMPLATE ? (
			<span>
				{' '}
				and the term of whichever {taxonomyLabel || taxonomyName}{' '}
				archive page it renders on
			</span>
		) : null;
	const fixedFilter =
		termSource === TERM_SOURCE.FIXED && taxonomyTermSlug ? (
			<span>
				{' '}
				and <pre>{taxonomyTermSlug}</pre> {taxonomyName}
			</span>
		) : null;

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
					{templateFilter}
					{fixedFilter}.
				</p>
			)}
			{newBlockAreaName && (
				<p>
					This area will render the latest public{' '}
					<pre>block_module</pre> that is in the new{' '}
					<pre>{newBlockAreaName}</pre> block area
					{templateFilter}
					{fixedFilter}.
				</p>
			)}
		</Step>
	);
}
