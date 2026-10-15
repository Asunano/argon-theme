import './editor.scss';
import { argonBlockClass, argonBlockAnchor, argonColorClass, argonColorStyle } from './../block-utils.js';
import { ArgonColorControl } from './../components/color-control.js';
import { __ } from './../i18n/i18n.js';
import {
	RichText,
	MediaUpload,
	BlockControls,
	AlignmentToolbar,
	RichTextToolbarButton,
	InspectorControls,
	PanelColorSettings
} from "@wordpress/block-editor";
import {
	TextControl,
	ToggleControl,
	Panel, PanelBody, PanelRow
} from '@wordpress/components';

const { registerBlockType } = wp.blocks;

const clamp = (num, min, max) => Math.min(Math.max(num, min), max);

registerBlockType('argon/progressbar', {
	apiVersion: 2,
	title: __('进度条'),
	icon: 'chart-bar',
	category: 'argon',
	supports: {
		align: ['wide', 'full'],
		anchor: true,
		className: true,
	},
	keywords: [
		'argon',
		__('进度条')
	],
	attributes: {
		color: {
			type: 'string',
			default: '#7889e8'
		},
		title: {
			type: 'string',
			default: ''
		},
		progress: {
			type: 'number',
			default: 50
		},
		show_title: {
			type: 'bool',
			default: true
		}
	},
	edit: (props) => {
		const onChangeTitle = (value) => {
			props.setAttributes({ title: value });
		};
		const onChangeColor = (value) => {
			props.setAttributes({ color: (value || "#5e72e4") });
		}
		const onChangeProgress = (value) => {
			props.setAttributes({ progress: clamp(Number(value), 0, 100) });
		}
		const onTitleStatusChange = (value) => {
			props.setAttributes({ show_title: value });
		}
		return (
			<div>
				<div className={argonBlockClass("progress-wrapper", props)} {...argonBlockAnchor(props)}>
					<div className="progress-info">
						{props.attributes.show_title &&
							<div className="progress-label">
								<RichText
									tagName="span"
									placeholder={__("标题")}
									value={props.attributes.title}
									onChange={onChangeTitle}
								/>
							</div>
						}
						<div className="progress-percentage">
							<span>{props.attributes.progress}%</span>
						</div>
					</div>
					<div className="progress">
						<div className={`progress-bar ${argonColorClass('bg', props.attributes.color)}`.trim()} style={{ width: props.attributes.progress.toString() + "%", ...(argonColorStyle('bg', props.attributes.color, 'backgroundColor') || {}) }} />
					</div>
				</div>
				<InspectorControls key="setting">
					<PanelBody title={__("区块设置")} icon={"more"} initialOpen={true}>
						<PanelRow>
							<div id="gutenpride-controls">
								<fieldset>
									<PanelRow>{__('颜色', 'argon')}</PanelRow>
									<ArgonColorControl value={props.attributes.color} onChange={onChangeColor} />
								</fieldset>
								<fieldset>
									<PanelRow>{__('部件', 'argon')}</PanelRow>
									<ToggleControl
										label={__('标题', 'argon')}
										checked={props.attributes.show_title}
										onChange={onTitleStatusChange}
									/>
								</fieldset>
								<fieldset>
									<PanelRow>{__('进度 (%)', 'argon')}</PanelRow>
									<input type="number" min="0" max="100" step="1" value={props.attributes.progress} onChange={e => onChangeProgress(e.currentTarget.value)}/>
								</fieldset>
							</div>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</div>
		);
	},
	save: (props) => {
		return (
			<div className={argonBlockClass("progress-wrapper", props)} {...argonBlockAnchor(props)}>
				<div className="progress-info">
					{props.attributes.show_title &&
						<div className="progress-label">
							<span dangerouslySetInnerHTML={{ __html: props.attributes.title }}/>
						</div>
					}
					<div className="progress-percentage">
						<span>{props.attributes.progress}%</span>
					</div>
				</div>
				<div className="progress">
					{/* 颜色改走 Bootstrap 的 bg-<name> 类（与短代码一致）；
					    预设色走类名，自定义色才写行内样式。 */}
					<div className={`progress-bar ${argonColorClass('bg', props.attributes.color)}`.trim()} style={{ width: props.attributes.progress.toString() + "%", ...(argonColorStyle('bg', props.attributes.color, 'backgroundColor') || {}) }} />
				</div>
			</div>
		);
	},
	/* 旧版 markup 声明：阶段 3 之前进度条颜色写死在行内 style 里，没有 bg-<name> 类。
	   原样声明在这里，已存文章就不会被判为「此区块包含意外或无效的内容」。 */
	deprecated: [
		{
			save: (props) => {
				return (
					<div className={argonBlockClass("progress-wrapper", props)} {...argonBlockAnchor(props)}>
						<div className="progress-info">
							{props.attributes.show_title &&
								<div className="progress-label">
									<span dangerouslySetInnerHTML={{ __html: props.attributes.title }}/>
								</div>
							}
							<div className="progress-percentage">
								<span>{props.attributes.progress}%</span>
							</div>
						</div>
						<div className="progress">
							<div className="progress-bar" style={{ width: props.attributes.progress.toString() + "%", backgroundColor: props.attributes.color }} />
						</div>
					</div>
				);
			},
		},
	],
});
