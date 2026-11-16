/**
 * argon/bilibili —— B 站视频（#532）
 *
 * 对应短代码 [bilibili]BV1xx411c7mD[/bilibili] 或 [bilibili url="..." mode="iframe"]
 *
 * 动态区块：卡片信息（标题 / UP 主 / 封面 / 时长）由服务端拉 B 站 API
 * （transient 缓存 6 小时，失败自动降级为无信息卡片），与短代码共用
 * argon_render_bilibili()。前台点击卡片时才插入播放器 iframe。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/bilibili', {
	apiVersion: 2,
	title: __('Bilibili 视频'),
	icon: 'video-alt3',
	category: 'argon',
	keywords: [ 'argon', 'bilibili', 'B站', __('视频') ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		input: {
			type: 'string',
			default: '',
		},
		mode: {
			type: 'string',
			default: 'card',
		},
		page: {
			type: 'integer',
			default: 0,
		},
		autoplay: {
			type: 'boolean',
			default: false,
		},
		danmaku: {
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
								label={__('BV 号 / av 号 / 视频链接')}
								value={props.attributes.input}
								onChange={(value) => props.setAttributes({ input: value })}
							/>
						</PanelRow>
						<PanelRow>
							<fieldset>
								<SelectControl
									label={__('嵌入模式')}
									value={props.attributes.mode}
									options={[
										{ label: __('信息卡（点击加载）'), value: 'card' },
										{ label: __('直接嵌入'), value: 'iframe' },
									]}
									onChange={(value) => props.setAttributes({ mode: value })}
								/>
							</fieldset>
						</PanelRow>
						<PanelRow>
							<TextControl
								type="number"
								label={__('分 P（可选）')}
								value={props.attributes.page}
								onChange={(value) => props.setAttributes({ page: parseInt(value || 0) })}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={__('自动播放')}
								checked={props.attributes.autoplay}
								onChange={(value) => props.setAttributes({ autoplay: value })}
							/>
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={__('弹幕')}
								checked={props.attributes.danmaku}
								onChange={(value) => props.setAttributes({ danmaku: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender
					block="argon/bilibili"
					attributes={props.attributes}
				/>
			</div>
		);
	},
	/* 动态区块：前台 HTML 全部由 PHP 的 render_callback 生成 */
	save: () => null,
});
