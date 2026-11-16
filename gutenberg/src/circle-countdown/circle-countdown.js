/**
 * argon/circle-progress 与 argon/countdown —— 圆形进度 / 倒计时
 *
 * 对应短代码：
 *   [progressbar-circle percentage="70" size="96" stroke="8" color="indigo" label=""]
 *   [countdown date="2026-12-31 23:59:59" title="距离上线"]已结束时的文案[/countdown]
 *
 * 两者都是动态区块，渲染直接复用短代码函数（结构只有一份实现）。
 * 倒计时的时间在服务端按站点时区解析成 UNIX 时间戳下发，前端只负责 ticking，
 * 这样不会因为访客本地时区不同而算错。
 */

import { __ } from './../i18n/i18n.js';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow, RangeControl, SelectControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/circle-progress', {
	apiVersion: 2,
	title: __('圆形进度'),
	icon: 'chart-pie',
	category: 'argon',
	keywords: [ 'argon', __('进度'), __('圆环'), 'circle', 'progress' ],
	supports: {
		anchor: true,
		className: true,
	},
	attributes: {
		percentage: { type: 'integer', default: 70 },
		size: { type: 'integer', default: 96 },
		stroke: { type: 'integer', default: 8 },
		color: { type: 'string', default: 'indigo' },
		label: { type: 'string', default: '' },
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
								label={__('百分比')}
								value={a.percentage}
								min={0}
								max={100}
								onChange={(value) => props.setAttributes({ percentage: value })}
							/>
						</PanelRow>
						<PanelRow>
							<RangeControl
								label={__('直径 (px)')}
								value={a.size}
								min={40}
								max={240}
								onChange={(value) => props.setAttributes({ size: value })}
							/>
						</PanelRow>
						<PanelRow>
							<RangeControl
								label={__('线宽 (px)')}
								value={a.stroke}
								min={2}
								max={30}
								onChange={(value) => props.setAttributes({ stroke: value })}
							/>
						</PanelRow>
						<PanelRow>
							<fieldset>
								<SelectControl
									label={__('颜色')}
									value={a.color}
									options={[
										{ label: __('主题色'), value: 'indigo' },
										{ label: __('绿色'), value: 'green' },
										{ label: __('红色'), value: 'red' },
										{ label: __('橙色'), value: 'orange' },
										{ label: __('蓝色'), value: 'blue' },
										{ label: __('灰色'), value: 'grey' },
									]}
									onChange={(value) => props.setAttributes({ color: value })}
								/>
							</fieldset>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('中心文字')}
								help={__('留空则显示百分比')}
								value={a.label}
								onChange={(value) => props.setAttributes({ label: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block="argon/circle-progress" attributes={props.attributes} />
			</div>
		);
	},
	save: () => null,
});

registerBlockType('argon/countdown', {
	apiVersion: 2,
	title: __('倒计时'),
	icon: 'clock',
	category: 'argon',
	keywords: [ 'argon', __('倒计时'), 'countdown', 'timer' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		date: { type: 'string', default: '' },
		title: { type: 'string', default: '' },
		finished: { type: 'string', default: '' },
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
								label={__('目标时间')}
								help={__('按站点时区解析，例如 2026-12-31 23:59:59')}
								value={a.date}
								onChange={(value) => props.setAttributes({ date: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('标题')}
								value={a.title}
								onChange={(value) => props.setAttributes({ title: value })}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={__('结束后的文案')}
								help={__('留空显示「已结束」')}
								value={a.finished}
								onChange={(value) => props.setAttributes({ finished: value })}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block="argon/countdown" attributes={props.attributes} />
			</div>
		);
	},
	save: () => null,
});
