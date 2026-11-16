/**
 * argon/link-card —— 外链卡片
 *
 * 对应短代码 [link-card url="..." title="..." desc="..." icon="..."]
 *
 * 动态区块：前台 HTML 由 PHP 的 shortcode_link_card() 生成，与短代码共用一份，
 * 两边外观与兜底逻辑（无图标时用首字头像、无标题时用域名）不会跑偏。
 *
 * 刻意不自动抓取目标站点的标题 / favicon —— 那样每个访客的 IP 与 Referer 都会泄漏给
 * 第三方站点，而且首屏会变慢。图标留空时用纯 CSS 首字头像。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, TextControl, TextareaControl } from '@wordpress/components';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/link-card', {
	apiVersion: 2,
	title: __('外链卡片'),
	icon: 'admin-links',
	category: 'argon',
	keywords: [ 'argon', __('外链'), __('链接卡片'), 'link', 'card' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		url: {
			type: 'string',
			default: '',
		},
		title: {
			type: 'string',
			default: '',
		},
		desc: {
			type: 'string',
			default: '',
		},
		icon: {
			type: 'string',
			default: '',
		},
	},
	edit: (props) => {
		const blockProps = useBlockProps();
		return (
			<div {...blockProps}>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<TextControl
								label={__('链接地址')}
								value={props.attributes.url}
								onChange={(value) => props.setAttributes({ url: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('标题')}
								help={__('留空时显示域名')}
								value={props.attributes.title}
								onChange={(value) => props.setAttributes({ title: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextareaControl
								label={__('描述')}
								value={props.attributes.desc}
								onChange={(value) => props.setAttributes({ desc: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('图标地址')}
								help={__('留空时用标题首字生成头像；填地址则直接引用，主题不会去抓取目标站点')}
								value={props.attributes.icon}
								onChange={(value) => props.setAttributes({ icon: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<div className="argon-link-card argon-link-card-editor">
					<span className="argon-link-card-icon">
						{props.attributes.icon
							? <img src={props.attributes.icon} alt="" />
							: <span className="argon-link-card-icon-text">{(props.attributes.title || props.attributes.url || '?').charAt(0)}</span>}
					</span>
					<span className="argon-link-card-body">
						<span className="argon-link-card-title">{props.attributes.title || props.attributes.url || __('链接地址（右侧栏填写）')}</span>
						<span className="argon-link-card-desc">{props.attributes.desc || __('描述（可选，右侧栏填写）')}</span>
					</span>
					<span className="argon-link-card-arrow"><i className="fa fa-angle-right"></i></span>
				</div>
			</div>
		);
	},
	/* 动态区块：前台 HTML 全部由 PHP 的 render_callback 生成 */
	save: () => null,
});
