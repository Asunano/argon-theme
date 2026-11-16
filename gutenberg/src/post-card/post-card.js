/**
 * argon/post-card 与 argon/post-list —— 文章引用卡 / 文章列表
 *
 * 对应短代码：
 *   [post-card id="123"] 或 [post-card url="https://…"]     ← 引用站内某篇文章
 *   [post-list tag="x" category="y" count="4" orderby="date"]
 *
 * 两者都是动态区块：卡片外观、密码保护文章的摘要屏蔽、只认已发布文章的过滤
 * 全在 PHP 侧（argon_render_post_card / shortcode_post_list），与短代码共用一份实现。
 *
 * 引用关系（反向链接）由 PHP 在保存文章时写入目标文章的 post meta，
 * 与区块/短代码哪种写法无关——两种写法都能被 argon_extract_post_card_targets() 识别。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, SelectControl, TextControl, ToggleControl } from '@wordpress/components';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/post-card', {
	apiVersion: 2,
	title: __('文章引用卡'),
	icon: 'admin-page',
	category: 'argon',
	keywords: [ 'argon', __('引用'), __('文章卡片'), 'post', 'card' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		id: {
			type: 'integer',
			default: 0,
		},
		url: {
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
								type="number"
								label={__('文章 ID')}
								help={__('在文章的编辑页地址里能找到 post=数字')}
								value={props.attributes.id}
								onChange={(value) => props.setAttributes({ id: parseInt(value || 0) })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('或填文章链接')}
								help={__('填了链接时优先按链接解析，文章 ID 可以留空')}
								value={props.attributes.url}
								onChange={(value) => props.setAttributes({ url: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<div className="argon-post-card argon-post-card-editor">
					<span className="argon-post-card-thumb argon-post-card-thumb-text">{props.attributes.id ? '#' : <i className="fa fa-file-text-o"></i>}</span>
					<span className="argon-post-card-body">
						<span className="argon-post-card-title">
							{props.attributes.url || (props.attributes.id ? `文章 #${props.attributes.id}` : __('文章 ID 或链接（右侧栏填写）'))}
						</span>
						<span className="argon-post-card-excerpt">{__('标题、摘要与封面将在前台渲染')}</span>
						<span className="argon-post-card-meta">post-card</span>
					</span>
				</div>
			</div>
		);
	},
	/* 动态区块：前台 HTML 全部由 PHP 的 render_callback 生成 */
	save: () => null,
});

registerBlockType('argon/post-list', {
	apiVersion: 2,
	title: __('文章列表'),
	icon: 'list-view',
	category: 'argon',
	keywords: [ 'argon', __('文章列表'), __('相关文章'), 'post', 'list' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		tag: {
			type: 'string',
			default: '',
		},
		category: {
			type: 'string',
			default: '',
		},
		count: {
			type: 'integer',
			default: 4,
		},
		orderby: {
			type: 'string',
			default: 'date',
		},
		include_current: {
			type: 'boolean',
			default: false,
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
								label={__('标签（可选）')}
								help={__('填标签别名（slug），多个用逗号分隔')}
								value={props.attributes.tag}
								onChange={(value) => props.setAttributes({ tag: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('分类（可选）')}
								help={__('填分类别名（slug）')}
								value={props.attributes.category}
								onChange={(value) => props.setAttributes({ category: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								type="number"
								label={__('数量')}
								help={__('1–20，默认 4')}
								value={props.attributes.count}
								onChange={(value) => props.setAttributes({ count: parseInt(value || 4) })}
							/>
						</PanelRow>
						<PanelRow>
							<fieldset>
								<SelectControl
									label={__('排序')}
									value={props.attributes.orderby}
									options={[
										{ label: __('按发布时间'), value: 'date' },
										{ label: __('随机'), value: 'rand' },
									]}
									onChange={(value) => props.setAttributes({ orderby: value })}
								/>
							</fieldset>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={__('包含当前文章')}
								checked={props.attributes.include_current}
								onChange={(value) => props.setAttributes({ include_current: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<div className="argon-post-list argon-post-list-editor">
					<div className="argon-post-list-editor-note">
						{__('前台将显示文章卡片列表')}
						{props.attributes.tag ? ' · ' + __('标签') + ': ' + props.attributes.tag : ''}
						{props.attributes.category ? ' · ' + __('分类') + ': ' + props.attributes.category : ''}
						{` · ${props.attributes.count || 4} ` + __('篇')}
					</div>
					{[1, 2, 3].map((i) => (
						<div key={i} className="argon-post-card argon-post-card-skeleton">
							<span className="argon-post-card-thumb argon-post-card-thumb-text"><i className="fa fa-file-text-o"></i></span>
							<span className="argon-post-card-body">
								<span className="argon-post-card-title"><span className="argon-editor-skeleton-bar" style={{ width: '55%' }}></span></span>
								<span className="argon-post-card-excerpt"><span className="argon-editor-skeleton-bar" style={{ width: '80%' }}></span></span>
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
