/**
 * 共用的颜色选择控件。
 *
 * 为什么不用裸 ColorPalette：
 *   原来各区块直接用 ColorPalette，用户看到的是一排没有名字的色块 ——
 *   想给「警告」配橙色、给「提醒」配蓝色，只能靠肉眼猜。
 *   而且短代码侧用的是【颜色名】（[label color="orange"]），两边对不上。
 *
 * 这个控件的做法：
 *   · 主选择是一个下拉框，列出【带中文名的预设色】—— 主色 / 成功 / 危险 /
 *     信息 / 警告 / 深色 / 灰，名字与短代码的语义一一对应
 *   · 另有一个「自定义」选项，选中后才出现调色盘，允许任意颜色
 *   · 属性值始终存【十六进制色值】，与既有区块完全兼容，不需要迁移
 *
 * 预设色与短代码的对应关系：
 *   主色 primary(#7889e8)  → [label color=indigo] / alert-primary
 *   成功 success(#4fd69c)  → [label color=green]  / alert-success
 *   危险 danger (#f75676)  → [label color=red]    / alert-danger
 *   信息 info   (#37d5f2)  → [label color=blue]   / alert-info
 *   警告 warning(#fc7c5f)  → [label color=orange] / alert-warning
 *   深色 default(#3c4d69)  → alert-default
 *   灰   grey   (#888888)  → alert-grey
 */

import { __ } from './../i18n/i18n.js';
import { SelectControl, ColorPalette } from '@wordpress/components';

/* 预设色：slug 用于内部判断，hex 是实际存进属性的值，name 是面板上显示的中文名 */
export const ARGON_COLOR_PRESETS = [
	{ slug: 'primary', name: __('主色'), hex: '#7889e8' },
	{ slug: 'success', name: __('成功'), hex: '#4fd69c' },
	{ slug: 'danger', name: __('危险'), hex: '#f75676' },
	{ slug: 'info', name: __('信息'), hex: '#37d5f2' },
	{ slug: 'warning', name: __('警告'), hex: '#fc7c5f' },
	{ slug: 'default', name: __('深色'), hex: '#3c4d69' },
	{ slug: 'grey', name: __('灰'), hex: '#888888' },
];

/* 调色盘里可选的色块（自定义时用）—— 预设 7 色 + 几个常用色 */
const CUSTOM_SWATCHES = [
	...ARGON_COLOR_PRESETS.map((p) => ({ name: p.name, color: p.hex })),
	{ name: __('主题色'), color: '#5e72e4' },
	{ name: __('粉'), color: '#fa7298' },
	{ name: __('白'), color: '#ffffff' },
	{ name: __('黑'), color: '#000000' },
];

/**
 * 由色值反查预设 slug；不是预设色则返回 'custom'。
 *
 * @param {string} color
 * @return {string}
 */
export function argonColorSlug(color) {
	const hex = String(color || '').toLowerCase();
	const hit = ARGON_COLOR_PRESETS.find((p) => p.hex === hex);
	return hit ? hit.slug : 'custom';
}

/**
 * 颜色选择控件。
 *
 * @param {Object}   props
 * @param {string}   props.value     当前色值（十六进制）
 * @param {Function} props.onChange  色值变化回调
 * @param {string}   props.label     控件标题，默认「颜色」
 * @return {JSX.Element}
 */
export function ArgonColorControl({ value, onChange, label }) {
	const slug = argonColorSlug(value);
	const options = [
		...ARGON_COLOR_PRESETS.map((p) => ({
			label: p.name,
			value: p.slug,
		})),
		{ label: __('自定义'), value: 'custom' },
	];

	return (
		<>
			<SelectControl
				label={label || __('颜色')}
				value={slug}
				options={options}
				onChange={(next) => {
					if (next === 'custom') {
						/* 切到自定义时保留当前色值，用户再在下面的调色盘里挑 */
						return;
					}
					const preset = ARGON_COLOR_PRESETS.find((p) => p.slug === next);
					if (preset) {
						onChange(preset.hex);
					}
				}}
			/>
			{slug === 'custom' && (
				<ColorPalette
					colors={CUSTOM_SWATCHES}
					value={value}
					onChange={(next) => onChange(next || value)}
				/>
			)}
		</>
	);
}
