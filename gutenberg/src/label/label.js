/**
 * argon/label —— 徽章 / 标签
 *
 * 对应短代码 [label color="orange" shape="round"]文字[/label]
 *
 * 短代码的输出是：
 *   <span class='badge badge-primary'>文字</span>
 *   <span class='badge badge-warning badge-pill'>文字</span>
 * 颜色名 → Bootstrap 类：
 *   indigo→primary  green→success  red→danger  orange→warning  blue→info
 * 这里用同一套 hex → 类的映射（argonColorClass），
 * 所以区块和短代码在前台长得一模一样，也都能被主题定制与暗色模式影响。
 */

import { __ } from './../i18n/i18n.js';
import { RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, PanelRow, SelectControl } from '@wordpress/components';
import { argonBlockClass, argonBlockAnchor, argonColorClass, argonColorStyle } from './../block-utils.js';
import { ArgonColorControl } from './../components/color-control.js';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/label', {
	apiVersion: 2,
	title: __('标签'),
	icon: 'tag',
	category: 'argon',
	keywords: [ 'argon', __('标签'), 'label', 'badge' ],
	supports: {
		align: [ 'wide', 'full' ],
		anchor: true,
		className: true,
	},
	attributes: {
		color: {
			type: 'string',
			default: '#7889e8',
		},
		shape: {
			type: 'string',
			default: 'square',
		},
		content: {
			type: 'string',
			default: '',
		},
	},
	edit: (props) => {
		const colorClass = argonColorClass('badge', props.attributes.color);
		const badgeClass = [
			'badge',
			colorClass,
			props.attributes.shape === 'round' ? 'badge-pill' : '',
		]
			.filter(Boolean)
			.join(' ');

		return (
			<div>
				<span
					className={badgeClass}
					style={argonColorStyle('badge', props.attributes.color, 'backgroundColor')}
				>
					<RichText
						tagName="span"
						placeholder={__('标签文字')}
						value={props.attributes.content}
						onChange={(value) => props.setAttributes({ content: value })}
					/>
				</span>
				<InspectorControls key="setting">
					<PanelBody title={__('区块设置')} icon="more" initialOpen={true}>
						<PanelRow>
							<div id="gutenpride-controls">
								<fieldset>
									<ArgonColorControl
										value={props.attributes.color}
										onChange={(value) => props.setAttributes({ color: value })}
									/>
								</fieldset>
								<fieldset>
									<SelectControl
										label={__('形状')}
										value={props.attributes.shape}
										options={[
											{ label: __('方形'), value: 'square' },
											{ label: __('圆角'), value: 'round' },
										]}
										onChange={(value) => props.setAttributes({ shape: value })}
									/>
								</fieldset>
							</div>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</div>
		);
	},
	save: (props) => {
		const colorClass = argonColorClass('badge', props.attributes.color);
		const badgeClass = [
			'badge',
			colorClass,
			props.attributes.shape === 'round' ? 'badge-pill' : '',
		]
			.filter(Boolean)
			.join(' ');

		return (
			<span
				className={argonBlockClass(badgeClass, props)}
				{...argonBlockAnchor(props)}
				style={argonColorStyle('badge', props.attributes.color, 'backgroundColor')}
			>
				<span dangerouslySetInnerHTML={{ __html: props.attributes.content }} />
			</span>
		);
	},
});
