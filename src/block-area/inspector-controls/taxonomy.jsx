/**
 * Internal Dependencies
 */
import TermSourceControl from '../term-source-control';

export default function TaxonomyControl({
	attributes,
	setAttributes,
	templateSlug,
}) {
	return (
		<TermSourceControl
			attributes={attributes}
			setAttributes={setAttributes}
			templateSlug={templateSlug}
		/>
	);
}
