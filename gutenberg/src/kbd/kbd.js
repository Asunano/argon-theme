/**
 * argon/kbd —— 键盘按键
 *
 * 对应短代码 [kbd]Ctrl + C[/kbd]
 *
 * 前台外观来自 style.css 既有的 article .post-content kbd 键帽样式，
 * 所以短代码与这里都只输出裸 <kbd>，不引入新类名 —— 老样式无缝衔接。
 * 编辑器里的预览样式由 src/shortcode-preview.scss 提供（跟随 dist/index.css 注入编辑器）。
 */

import { __ } from './../i18n/i18n.js';
import { RichText } from '@wordpress/block-editor';
import { argonBlockClass, argonBlockAnchor } from './../block-utils.js';

const { registerBlockType } = wp.blocks;

registerBlockType('argon/kbd', {
	apiVersion: 2,
	title: __('键盘按键'),
	icon: 'keyboard',
	category: 'argon',
	keywords: [ 'argon', __('键盘'), 'kbd', 'key' ],
	supports: {
		anchor: true,
		className: true,
	},
	attributes: {
		content: {
			type: 'string',
			default: '',
		},
	},
	edit: (props) => {
		return (
			<kbd
				style={{
					padding: '2px 6px',
					fontSize: '90%',
					color: '#fff',
					backgroundColor: '#333',
					borderRadius: '3px',
				}}
			>
				<RichText
					tagName="span"
					placeholder={__('按键，如 Ctrl + C')}
					value={props.attributes.content}
					onChange={(value) => props.setAttributes({ content: value })}
				/>
			</kbd>
		);
	},
	save: (props) => {
		return (
			<kbd className={argonBlockClass('', props)} {...argonBlockAnchor(props)}>
				<span dangerouslySetInnerHTML={{ __html: props.attributes.content }} />
			</kbd>
		);
	},
});
