/**
 * 区块共用的工具。
 *
 * 为什么不用 useBlockProps.save()：
 *   它会把 wp-block-<namespace>-<name> 这个类也一并带上，而 WordPress 本身
 *   在保存时已经会自动加它 —— 两处都加就会重复。
 *   这里手工拼 class，好处是【在没设自定义类、没选对齐时，产物与旧版完全一致】，
 *   老文章不会因为这次改动而失效（区块失效判定是逐字节比对 save 的输出）。
 *
 * 用法（在 save 里）：
 *   <div className={argonBlockClass('alert', props)} {...argonBlockAnchor(props)}>
 */

/**
 * 拼出根元素的 className。
 *
 * 【重要】没有任何类名时返回 undefined —— React 不会为 undefined 输出 class 属性。
 * 这一点是「不破坏老文章」的关键：像 timeline 那种根元素是裸 <div> 的区块，
 * 如果返回空串，产物会从 <div> 变成 <div class="">，与旧产物不一致，
 * WordPress 会判定「此区块包含意外或无效的内容」。
 *
 * @param {string} base  区块原有的类名（可以是空串）
 * @param {Object} props save/edit 收到的 props
 * @return {string|undefined}
 */
export function argonBlockClass(base, props) {
	const extra = [];

	// 对齐：WP 的约定是 alignwide / alignfull / alignleft / aligncenter / alignright
	const align = props && props.attributes ? props.attributes.align : undefined;
	if (align) {
		extra.push('align' + align);
	}

	// 编辑器侧边栏「额外的 CSS 类」——WP 通过 props.className 传进来
	if (props && props.className) {
		extra.push(props.className);
	}

	const cls = [base, ...extra].filter(Boolean).join(' ').trim();
	return cls === '' ? undefined : cls;
}

/**
 * 锚点：supports.anchor 打开后，WP 通过 props.anchor 传 id。
 * 没设时返回空对象，不会产生额外属性 —— 保证旧产物不变。
 *
 * @param {Object} props
 * @return {Object}
 */
export function argonBlockAnchor(props) {
	return props && props.anchor ? { id: props.anchor } : {};
}

/**
 * 预设色 → CSS 类后缀。
 *
 * 短代码用的是类名（alert-primary / admonition-success / bg-danger…），
 * 而区块编辑器一直用【行内样式】。行内样式优先级最高，导致主题定制与暗色模式
 * 都盖不住区块 —— 这是区块与短代码外观不一致的根源。
 *
 * 这里把预设色映射回类名；能映射的就不再写行内样式，于是 CSS 能正常生效。
 * 用户自己挑的任意色映射不到，仍然走行内样式（否则颜色就丢了）。
 */
const ARGON_PRESET_COLORS = {
	'#7889e8': 'primary', // Argon 主色（编辑器色板第一个）
	'#5e72e4': 'primary', // 主题色
	'#4fd69c': 'success', // green
	'#f75676': 'danger', // red
	'#37d5f2': 'info', // blue
	'#fc7c5f': 'warning', // orange
	'#3c4d69': 'default', // black
	'#888888': 'grey', // grey
};

/**
 * 把预设色转成 "<prefix>-<name>" 形式的类名；不是预设色则返回空串。
 *
 * @param {string} prefix 例如 'alert' / 'admonition' / 'collapse-block' / 'bg'
 * @param {string} color  形如 #7889e8
 * @return {string}
 */
export function argonColorClass(prefix, color) {
	if (!color) {
		return '';
	}
	const name = ARGON_PRESET_COLORS[String(color).toLowerCase()];
	return name ? prefix + '-' + name : '';
}

/**
 * 颜色对应的行内样式：只有【非预设色】才需要。
 * 返回 undefined 时 React 不输出 style 属性。
 *
 * @param {string} prefix
 * @param {string} color
 * @param {string} property 要设置的 CSS 属性名，如 'backgroundColor'
 * @return {Object|undefined}
 */
export function argonColorStyle(prefix, color, property) {
	if (!color || argonColorClass(prefix, color)) {
		return undefined;
	}
	return { [property]: color };
}
