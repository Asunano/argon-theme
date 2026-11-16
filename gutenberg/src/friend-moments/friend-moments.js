/**
 * argon/friend-moments —— 友链朋友圈（RSS 聚合）
 *
 * 对应短代码 [friend-moments count="20" days="120" title="友链朋友圈"]
 *
 * 动态区块：卡片 HTML 由 PHP 的 shortcode_friend_moments() 生成，与短代码共用一份。
 * 注意：SSR 渲染时读取的是朋友圈缓存（不触发实时抓取），因此首次使用前
 * 需在设置页点一次「立即刷新」，或等每小时 cron 预热。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, RangeControl, TextControl } from '@wordpress/components';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/friend-moments', {
	apiVersion: 2,
	title: __('友链朋友圈'),
	icon: 'rss',
	category: 'argon',
	keywords: [ 'argon', __('朋友圈'), __('友链'), 'rss', 'moments' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		count: {
			type: 'integer',
			default: 20,
		},
		days: {
			type: 'integer',
			default: 120,
		},
		title: {
			type: 'string',
			default: '',
		},
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
								label={__('展示条数')}
								value={a.count}
								min={1}
								max={100}
								onChange={(value) => props.setAttributes({ count: value })}
							/>
						</PanelRow>
						<PanelRow>
							<RangeControl
								label={__('收录天数范围')}
								help={__('仅展示最近 N 天内发布的文章')}
								value={a.days}
								min={1}
								max={3650}
								onChange={(value) => props.setAttributes({ days: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('标题')}
								help={__('留空显示「友链朋友圈」')}
								value={a.title}
								onChange={(value) => props.setAttributes({ title: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<div className="argon-fl-moments argon-fl-moments-editor">
					<div className="argon-fl-moments-title"><i className="fa fa-rss"></i> {props.attributes.title || __('友链朋友圈')}</div>
					<div className="argon-fl-moments-editor-hint">
						{__('前台聚合友链 RSS 最新文章；首次使用请先在主题设置里「立即刷新缓存」')}
					</div>
					{[1, 2, 3].map((i) => (
						<div key={i} className="argon-fl-moment argon-fl-moment-skeleton">
							<span className="argon-fl-moment-avatar argon-fl-moment-avatar-text"><i className="fa fa-user"></i></span>
							<span className="argon-fl-moment-body">
								<span className="argon-editor-skeleton-bar" style={{ width: '40%' }}></span>
								<span className="argon-editor-skeleton-bar" style={{ width: '85%' }}></span>
							</span>
						</div>
					))}
				</div>
			</div>
		);
	},
	/* 动态区块：前台 HTML 全部由 PHP 的 render_callback 生成 */
	save: () => null,
});
