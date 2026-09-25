/* eslint-disable max-lines-per-function */
/**
 * WordPress Dependencies
 */
import { useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Placeholder,
	__experimentalHStack as HStack,
} from '@wordpress/components';

/**
 * Internal Dependencies
 */
import { Intro, QueryA, QueryB, QueryC, SelectA, CreateA } from './steps';
import Icon from '../icon';
import { createBlockArea } from '../functions';
import { useBlockModuleTaxonomies } from '../hooks';
import {
	TERM_SOURCE,
	getTermSource,
	termSourcePatch,
	parseTaxonomyTemplate,
} from '../term-source';

const WIZARD_RESET = {
	wizardStep: 'intro',
	wizardNewBlockAreaName: '',
	wizardIsCreatingNewBlockArea: false,
	wizardTermSource: undefined,
};

const PREVIOUS_STEP = {
	'query-a': 'intro',
	'query-b': 'query-a',
	'query-c': 'query-b',
	'select-a': 'intro',
};

/**
 * @param {Object}   props
 * @param {Object}   props.attributes
 * @param {Function} props.setAttributes
 * @param {Array}    props.blockModules
 * @param {boolean}  props.isResolving
 * @param {Object}   props.context
 * @param {string}   props.clientId
 */
export default function BlockAreaWizard({
	attributes,
	setAttributes,
	blockModules,
	isResolving,
	context,
	clientId,
}) {
	const { templateSlug } = context;
	const { taxonomies } = useBlockModuleTaxonomies();
	const taxonomySlugs = useMemo(
		() => taxonomies.map((taxonomy) => taxonomy.slug),
		[taxonomies]
	);
	const parsedTemplate = useMemo(
		() => parseTaxonomyTemplate(templateSlug, taxonomySlugs),
		[templateSlug, taxonomySlugs]
	);

	const blockAreaSlug = attributes?.blockAreaSlug ?? '';
	const taxonomyName = attributes?.taxonomyName ?? '';
	const taxonomyTermSlug = attributes?.taxonomyTermSlug ?? '';

	const wizardStep = attributes?.wizardStep ?? 'intro';
	const wizardNewBlockAreaName = attributes?.wizardNewBlockAreaName ?? '';
	const wizardIsCreatingNewBlockArea = Boolean(
		attributes?.wizardIsCreatingNewBlockArea
	);

	const effectiveSource = useMemo(() => {
		if (attributes?.wizardTermSource) {
			return attributes.wizardTermSource;
		}
		const fromAttrs = getTermSource(attributes);
		if (fromAttrs !== TERM_SOURCE.NONE) {
			return fromAttrs;
		}
		if (parsedTemplate) {
			return TERM_SOURCE.TEMPLATE;
		}
		return TERM_SOURCE.NONE;
	}, [attributes, parsedTemplate]);

	const templateTaxonomyLabel =
		taxonomies.find(
			(taxonomy) => taxonomy.slug === parsedTemplate?.taxonomy
		)?.name || parsedTemplate?.taxonomy;

	const setBlockAreaSlug = useCallback(
		(value) => {
			setAttributes({
				blockAreaSlug: value,
				blockAreaQueryComplete: false,
			});
		},
		[setAttributes]
	);

	const handleSourceChange = useCallback(
		(next) => {
			setAttributes({
				wizardTermSource: next,
				...termSourcePatch(next, {
					taxonomyName:
						next === TERM_SOURCE.TEMPLATE
							? parsedTemplate?.taxonomy
							: taxonomyName,
					taxonomyTermSlug,
				}),
			});
		},
		[setAttributes, parsedTemplate, taxonomyName, taxonomyTermSlug]
	);

	const canProceedQueryA = useMemo(() => {
		if (wizardIsCreatingNewBlockArea) {
			return wizardNewBlockAreaName.trim().length >= 3;
		}
		return Boolean(blockAreaSlug && blockAreaSlug.length > 0);
	}, [wizardIsCreatingNewBlockArea, wizardNewBlockAreaName, blockAreaSlug]);

	const canProceedQueryB = useMemo(() => {
		if (
			effectiveSource === TERM_SOURCE.NONE ||
			effectiveSource === TERM_SOURCE.TEMPLATE
		) {
			return true;
		}
		return Boolean(taxonomyTermSlug && taxonomyTermSlug.length > 0);
	}, [effectiveSource, taxonomyTermSlug]);

	const handleQueryCInsert = useCallback(async () => {
		const newAttrs = {
			...termSourcePatch(effectiveSource, {
				taxonomyName:
					effectiveSource === TERM_SOURCE.TEMPLATE
						? parsedTemplate?.taxonomy
						: taxonomyName,
				taxonomyTermSlug,
			}),
			blockAreaQueryComplete: true,
			...WIZARD_RESET,
		};
		if (wizardNewBlockAreaName.trim()) {
			const newBlockAreaSlug = await createBlockArea(
				wizardNewBlockAreaName.trim()
			);
			if (newBlockAreaSlug) {
				newAttrs.blockAreaSlug = newBlockAreaSlug;
				setAttributes(newAttrs);
			}
			return;
		}
		if (blockAreaSlug) {
			newAttrs.blockAreaSlug = blockAreaSlug;
			setAttributes(newAttrs);
		}
	}, [
		effectiveSource,
		parsedTemplate,
		taxonomyName,
		taxonomyTermSlug,
		wizardNewBlockAreaName,
		blockAreaSlug,
		setAttributes,
	]);

	const primaryToolbar = useMemo(() => {
		if (wizardStep === 'select-a') {
			return null;
		}
		if (wizardStep === 'query-a') {
			return {
				text: __('Next', 'prc-platform-core'),
				variant: 'secondary',
				disabled: !canProceedQueryA,
				onClick: () => {
					setAttributes({
						wizardStep: 'query-b',
					});
				},
			};
		}
		if (wizardStep === 'query-b') {
			return {
				text: __('Next', 'prc-platform-core'),
				variant: 'secondary',
				disabled: !canProceedQueryB,
				onClick: () => {
					setAttributes({
						wizardStep: 'query-c',
					});
				},
			};
		}
		if (wizardStep === 'query-c') {
			return {
				text: __('Insert Block Area', 'prc-platform-core'),
				variant: 'primary',
				disabled: false,
				onClick: () => {
					void handleQueryCInsert();
				},
			};
		}
		return null;
	}, [
		wizardStep,
		canProceedQueryA,
		canProceedQueryB,
		setAttributes,
		handleQueryCInsert,
	]);

	const handleBack = useCallback(() => {
		const prev = PREVIOUS_STEP[wizardStep];
		if (!prev) {
			return;
		}
		const patch = { wizardStep: prev };
		if (prev === 'intro') {
			Object.assign(patch, {
				wizardIsCreatingNewBlockArea: false,
				wizardNewBlockAreaName: '',
			});
		}
		setAttributes(patch);
	}, [wizardStep, setAttributes]);

	const showToolbar = ['query-a', 'query-b', 'query-c', 'select-a'].includes(
		wizardStep
	);

	const allowBack =
		wizardStep === 'select-a' ||
		(wizardStep !== 'intro' &&
			wizardStep !== 'create-a' &&
			Boolean(PREVIOUS_STEP[wizardStep]));

	return (
		<Placeholder
			label={__('Block Area', 'prc-platform-core')}
			isColumnLayout={true}
			icon={() => <Icon color={null} />}
		>
			<div className="block-area-edit__placeholder-inner">
				{['intro', 'create-a'].includes(wizardStep) && (
					<Intro
						isResolving={isResolving}
						blockModules={blockModules}
						setAttributes={setAttributes}
					/>
				)}
				{wizardStep === 'query-a' && (
					<QueryA
						blockAreaSlug={blockAreaSlug}
						setBlockAreaSlug={setBlockAreaSlug}
						wizardNewBlockAreaName={wizardNewBlockAreaName}
						wizardIsCreatingNewBlockArea={
							wizardIsCreatingNewBlockArea
						}
						setAttributes={setAttributes}
					/>
				)}
				{wizardStep === 'query-b' && (
					<QueryB
						attributes={attributes}
						setAttributes={setAttributes}
						templateSlug={templateSlug}
						source={effectiveSource}
						onSourceChange={handleSourceChange}
					/>
				)}
				{wizardStep === 'query-c' && (
					<QueryC
						blockAreaSlug={blockAreaSlug}
						taxonomyName={taxonomyName}
						taxonomyTermSlug={taxonomyTermSlug}
						termSource={effectiveSource}
						taxonomyLabel={templateTaxonomyLabel}
						newBlockAreaName={wizardNewBlockAreaName}
					/>
				)}
				{wizardStep === 'select-a' && (
					<SelectA
						clientId={clientId}
						onSelect={({ id }) => {
							setAttributes({
								ref: id,
								blockAreaQueryComplete: true,
								...WIZARD_RESET,
							});
						}}
						onClose={() => {
							setAttributes({ wizardStep: 'intro' });
						}}
					/>
				)}
				{wizardStep === 'create-a' && (
					<CreateA
						onCreate={(id) => {
							setAttributes({
								ref: id,
								blockAreaQueryComplete: true,
								...WIZARD_RESET,
							});
						}}
						setAttributes={setAttributes}
					/>
				)}
				{showToolbar && (
					<HStack
						className="block-area-edit__toolbar"
						spacing="2"
						style={{ maxWidth: 'fit-content' }}
					>
						{allowBack && (
							<Button variant="link" onClick={handleBack}>
								{__('Back', 'prc-platform-core')}
							</Button>
						)}
						{wizardStep !== 'select-a' && primaryToolbar && (
							<Button
								__next40pxDefaultSize
								variant={primaryToolbar.variant}
								disabled={primaryToolbar.disabled}
								onClick={primaryToolbar.onClick}
							>
								{primaryToolbar.text}
							</Button>
						)}
					</HStack>
				)}
			</div>
		</Placeholder>
	);
}
