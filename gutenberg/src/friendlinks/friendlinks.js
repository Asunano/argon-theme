/**
 * argon/friendlinks —— 友链列表
 *
 * 对应短代码 [friendlinks style="2-big" sort="name" order="ASC"]
 *
 * 这是【动态区块】：友链数据来自 WordPress 自带的链接管理器
 * （get_bookmarks()），只能在服务端取，所以：
 *   · save 返回 null —— 前台 HTML 完全由 PHP 的 render_callback 生成
 *   · edit 用 ServerSideRender 直接渲染服务端结果，编辑器所见即前台所得
 *
 * 这样短代码与区块共用同一段 PHP 渲染逻辑，两边不可能跑偏。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/friendlinks', {
	apiVersion: 2,
	title: __('友链列表'),
	icon: 'admin-links',
	category: 'argon',
	keywords: [ 'argon', __('友链'), 'friendlinks', 'links' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		style: {
			type: 'string',
			default: '1',
		},
		sort: {
			type: 'string',
			default: 'name',
		},
		order: {
			type: 'string',
			default: 'ASC',
		},
	},
	edit: (props) => {
		const blockProps = useBlockProps();
		return (
			<div {...blockProps}>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<div id="gutenpride-controls">
								<fieldset>
									<SelectControl
										label={__('样式')}
										value={props.attributes.style}
										options={[
											{ label: __('卡片（单列）'), value: '1' },
											{ label: __('卡片（方形头像）'), value: '1-square' },
											{ label: __('紧凑（双列）'), value: '2' },
											{ label: __('紧凑大图'), value: '2-big' },
										]}
										onChange={(value) => props.setAttributes({ style: value })}
									/>
								</fieldset>
								<fieldset>
									<SelectControl
										label={__('排序字段')}
										value={props.attributes.sort}
										options={[
											{ label: __('名称'), value: 'name' },
											{ label: __('链接 ID'), value: 'id' },
											{ label: __('网址'), value: 'url' },
											{ label: __('评分'), value: 'rating' },
											{ label: __('排序值'), value: 'menu_order' },
										]}
										onChange={(value) => props.setAttributes({ sort: value })}
									/>
								</fieldset>
								<fieldset>
									<SelectControl
										label={__('排序方向')}
										value={props.attributes.order}
										options={[
											{ label: __('升序'), value: 'ASC' },
											{ label: __('降序'), value: 'DESC' },
										]}
										onChange={(value) => props.setAttributes({ order: value })}
									/>
								</fieldset>
							</div>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender
					block="argon/friendlinks"
					attributes={props.attributes}
				/>
			</div>
		);
	},
	/* 动态区块：前台 HTML 全部由 PHP 的 render_callback 生成 */
	save: () => null,
});
