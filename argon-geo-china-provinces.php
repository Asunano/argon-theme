<?php
if (!defined('ABSPATH')) exit;

/**
 * 中国各省 / 直辖市 / 自治区 / 特别行政区的英文 -> 中文映射。
 * ip.sb 的 geoip 接口只返回英文（如 "Guangdong"），本文件用于在服务端
 * 把中国（country_code = CN）的 region 字段翻译成中文。其他国家一律保留英文。
 */
function argon_cn_province_zh($province_en){
	static $map = array(
		'Beijing'                            => '北京',
		'Tianjin'                            => '天津',
		'Shanghai'                           => '上海',
		'Chongqing'                         => '重庆',
		'Hebei'                             => '河北',
		'Shanxi'                            => '山西',
		'Liaoning'                          => '辽宁',
		'Jilin'                             => '吉林',
		'Heilongjiang'                      => '黑龙江',
		'Jiangsu'                           => '江苏',
		'Zhejiang'                          => '浙江',
		'Anhui'                             => '安徽',
		'Fujian'                            => '福建',
		'Jiangxi'                           => '江西',
		'Shandong'                          => '山东',
		'Henan'                             => '河南',
		'Hubei'                             => '湖北',
		'Hunan'                             => '湖南',
		'Guangdong'                         => '广东',
		'Guangxi'                           => '广西',
		'Guangxi Zhuang Autonomous Region'  => '广西壮族自治区',
		'Hainan'                            => '海南',
		'Sichuan'                           => '四川',
		'Guizhou'                           => '贵州',
		'Yunnan'                            => '云南',
		'Tibet'                             => '西藏',
		'Tibet Autonomous Region'           => '西藏自治区',
		'Shaanxi'                           => '陕西',
		'Gansu'                             => '甘肃',
		'Qinghai'                           => '青海',
		'Ningxia'                           => '宁夏',
		'Ningxia Hui Autonomous Region'     => '宁夏回族自治区',
		'Xinjiang'                          => '新疆',
		'Xinjiang Uygur Autonomous Region'  => '新疆维吾尔自治区',
		'Inner Mongolia'                    => '内蒙古',
		'Inner Mongolia Autonomous Region'  => '内蒙古自治区',
		'Hong Kong'                         => '香港',
		'Macao'                             => '澳门',
		'Macau'                             => '澳门',
		'Taiwan'                            => '台湾',
	);
	$province_en = trim($province_en);
	if (isset($map[$province_en])){
		return $map[$province_en];
	}
	return $province_en;
}

/**
 * 中国各省 / 直辖市 / 自治区 / 特别行政区的 ISO 3166-2 代码（如 GD、BJ）-> 中文映射。
 * 多数 geoip 接口（ipwho.is / ip.sb）同时返回 region（英文全称）与 region_code（ISO 代码），
 * 优先用代码匹配，比按英文全称匹配更稳（避免 "Guangdong" vs "Guangdong Sheng" 之类差异）。
 */
function argon_cn_province_code_zh($code){
	static $map = array(
		'BJ' => '北京', 'TJ' => '天津', 'SH' => '上海', 'CQ' => '重庆',
		'HE' => '河北', 'SX' => '山西', 'LN' => '辽宁', 'JL' => '吉林', 'HL' => '黑龙江',
		'JS' => '江苏', 'ZJ' => '浙江', 'AH' => '安徽', 'FJ' => '福建', 'JX' => '江西',
		'SD' => '山东', 'HA' => '河南', 'HB' => '湖北', 'HN' => '湖南', 'GD' => '广东',
		'GX' => '广西', 'HI' => '海南', 'SC' => '四川', 'GZ' => '贵州', 'YN' => '云南',
		'XZ' => '西藏', 'SN' => '陕西', 'GS' => '甘肃', 'QH' => '青海', 'NX' => '宁夏',
		'XJ' => '新疆', 'NM' => '内蒙古', 'HK' => '香港', 'MO' => '澳门', 'TW' => '台湾',
	);
	$code = trim($code);
	if (isset($map[$code])){
		return $map[$code];
	}
	return $code;
}
