/**
 * argon/toc 与 argon/show-more —— 文内目录 / 展开更多
 *
 * 对应短代码：
 *   [toc title="目录" max="3" collapsed="false"]
 *   [show-more lines="10" more="展开全文" less="收起"]内容[/show-more]
 *
 * toc 是动态区块：PHP 只输出空容器，目录项由 argontheme.js 的 argonBuildToc()
 * 扫描当前页面标题生成（Pjax 换页后需要重扫，所以不能固化在 HTML 里）。
 *
 * show-more 是 InnerBlocks 动态区块：save 输出 <InnerBlocks.Content />，
 * 前台由 render_callback 包上折叠容器。注意它的内容是【渐进披露】而非门控，
 * 内容始终在 HTML 里；需要保密请用「内容门控」区块。
 */

import { __ } from './../i18n/i18n.js';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, RangeControl, TextControl, ToggleControl } from '@wordpress/components';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/toc', {
	apiVersion: 2,
	title: __('文内目录'),
	icon: 'list-view',
	category: 'argon',
	keywords: [ 'argon', __('目录'), 'toc', 'catalog' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		title: { type: 'string', default: '' },
		max: { type: 'integer', default: 3 },
		collapsed: { type: 'boolean', default: false },
	},
	edit: (props) => {
		const blockProps = useBlockProps();
		const a = props.attributes;
		return (
			<div {...blockProps}>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<TextControl
								label={__('标题')}
								help={__('留空显示「目录」')}
								value={a.title}
								onChange={(value) => props.setAttributes({ title: value })}
							/>
						</PanelRow>
						<PanelRow>
							<RangeControl
								label={__('包含到几级标题')}
								help={__('1 = 只收 h1，3 = 收 h1–h3')}
								value={a.max}
								min={1}
								max={6}
								onChange={(value) => props.setAttributes({ max: value })}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={__('默认折叠')}
								checked={a.collapsed}
								onChange={(value) => props.setAttributes({ collapsed: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<div className="argon-toc argon-toc-editor">
					<div className="argon-toc-title">
						<i className="fa fa-list-ul"></i> {props.attributes.title || __('目录')}
						<i className="fa fa-angle-down argon-toc-toggle"></i>
					</div>
					<div className="argon-toc-body">
						{__('前台根据文内标题自动生成，最多收录到 h') + (props.attributes.max || 3) + ' ' + __('级')}
						<div className="argon-editor-skeleton-bar" style={{ width: '70%', marginTop: '8px' }}></div>
						<div className="argon-editor-skeleton-bar" style={{ width: '55%' }}></div>
						<div className="argon-editor-skeleton-bar" style={{ width: '62%' }}></div>
					</div>
				</div>
			</div>
		);
	},
	save: () => null,
});

registerBlockType('argon/show-more', {
	apiVersion: 2,
	title: __('展开更多'),
	icon: 'arrow-down-alt2',
	category: 'argon',
	keywords: [ 'argon', __('展开'), __('折叠'), 'show-more', 'expand' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		lines: { type: 'integer', default: 10 },
		more: { type: 'string', default: '' },
		less: { type: 'string', default: '' },
	},
	edit: (props) => {
		const blockProps = useBlockProps();
		const a = props.attributes;
		return (
			<div {...blockProps}>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<RangeControl
								label={__('折叠高度（行）')}
								value={a.lines}
								min={2}
								max={80}
								onChange={(value) => props.setAttributes({ lines: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('展开按钮文字')}
								value={a.more}
								onChange={(value) => props.setAttributes({ more: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('收起按钮文字')}
								value={a.less}
								onChange={(value) => props.setAttributes({ less: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<div
					style={{
						border: '1px dashed #c9c9d4',
						borderRadius: '8px',
						padding: '12px 16px',
					}}
				>
					<div style={{ fontSize: '12px', opacity: 0.65, marginBottom: '6px' }}>
						<i className="fa fa-angle-down"></i>{' '}
						{__('超过设定行数时折叠（内容始终在页面里，不是门控）')}
					</div>
					<InnerBlocks />
				</div>
			</div>
		);
	},
	save: () => <InnerBlocks.Content />,
});
