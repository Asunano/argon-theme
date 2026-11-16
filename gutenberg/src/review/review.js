/**
 * argon/review —— 评分卡（带 Review 结构化数据）
 *
 * 对应短代码：
 *   [review score="4.5" max="5" title="产品名" author="我"
 *           pros="轻量|免插件" cons="无自动更新"]评价正文[/review]
 *
 * 动态区块：可见部分与 JSON-LD 都由 PHP 的 argon_render_review() 生成，与短代码共用一份。
 * 结构化数据的收集发生在该函数内部（argon_review_collect），
 * 所以区块形态不需要再挂 render_block —— 也就不会出现"只有区块有 schema、短代码没有"的偏差。
 *
 * 优点/缺点在编辑器里按行填写，比逐条增删控件省事；渲染前会按换行或 | 切分。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const { registerBlockType } = wp.blocks;

const ITEM_TYPES = [
	{ label: __('产品', 'argon'), value: 'Product' },
	{ label: __('书籍', 'argon'), value: 'Book' },
	{ label: __('电影', 'argon'), value: 'Movie' },
	{ label: __('课程', 'argon'), value: 'Course' },
	{ label: __('软件', 'argon'), value: 'SoftwareApplication' },
	{ label: __('食谱', 'argon'), value: 'Recipe' },
	{ label: __('游戏', 'argon'), value: 'Game' },
];

registerBlockType('argon/review', {
	apiVersion: 2,
	title: __('评分卡'),
	icon: 'star-filled',
	category: 'argon',
	keywords: [ 'argon', __('评分'), __('评价'), 'review', 'rating' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		title: {
			type: 'string',
			default: '',
		},
		score: {
			type: 'number',
			default: 0,
		},
		max: {
			type: 'number',
			default: 5,
		},
		author: {
			type: 'string',
			default: '',
		},
		itemtype: {
			type: 'string',
			default: 'Product',
		},
		summary: {
			type: 'string',
			default: '',
		},
		pros: {
			type: 'string',
			default: '',
		},
		cons: {
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
					<PanelBody title={__('评分')} icon="star-filled" initialOpen={true}>
						<PanelRow>
							<TextControl
								label={__('标题')}
								help={__('被评价的对象名称，例如产品名 / 书名')}
								value={a.title}
								onChange={(value) => props.setAttributes({ title: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								type="number"
								step="0.1"
								label={__('分数')}
								value={a.score}
								onChange={(value) => props.setAttributes({ score: parseFloat(value || 0) })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								type="number"
								label={__('满分')}
								value={a.max}
								onChange={(value) => props.setAttributes({ max: parseFloat(value || 5) })}
							/>
						</PanelRow>
						<PanelRow>
							<fieldset>
								<SelectControl
									label={__('评价对象类型')}
									help={__('影响结构化数据，搜索引擎按它归类')}
									value={a.itemtype}
									options={ITEM_TYPES}
									onChange={(value) => props.setAttributes({ itemtype: value })}
								/>
							</fieldset>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('评价者')}
								help={__('留空则不写入结构化数据的作者字段')}
								value={a.author}
								onChange={(value) => props.setAttributes({ author: value })}
							/>
						</PanelRow>
					</PanelBody>
					<PanelBody title={__('内容')} icon="editor-alignleft" initialOpen={false}>
						<PanelRow>
							<TextareaControl
								label={__('评价正文')}
								value={a.summary}
								onChange={(value) => props.setAttributes({ summary: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextareaControl
								label={__('优点')}
								help={__('每行一条')}
								value={a.pros}
								onChange={(value) => props.setAttributes({ pros: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextareaControl
								label={__('缺点')}
								help={__('每行一条')}
								value={a.cons}
								onChange={(value) => props.setAttributes({ cons: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block="argon/review" attributes={props.attributes} />
			</div>
		);
	},
	/* 动态区块：前台 HTML 与 JSON-LD 全部由 PHP 生成 */
	save: () => null,
});
