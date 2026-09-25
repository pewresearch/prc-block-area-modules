/**
 * Internal Dependencies
 */
import TermSourceControl from '../../term-source-control';

/**
 * Choose how this block area filters by taxonomy.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes
 * @param {Function} props.setAttributes
 * @param {string}   props.templateSlug
 * @param {string}   props.source
 * @param {Function} props.onSourceChange
 * @return {import('react').JSX.Element} The component.
 */
export default function QueryB({
	attributes,
	setAttributes,
	templateSlug,
	source,
	onSourceChange,
}) {
	return (
		<TermSourceControl
			attributes={attributes}
			setAttributes={setAttributes}
			templateSlug={templateSlug}
			source={source}
			onSourceChange={onSourceChange}
		/>
	);
}
