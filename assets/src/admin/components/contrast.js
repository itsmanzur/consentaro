// WCAG 2.x contrast helpers. Banner copy is at most 18px regular, so it is
// "normal text" for WCAG: AA needs 4.5:1 (large-text 3:1 never applies).

const HEX = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i;

const toRgb = ( hex ) => {
	const match = HEX.exec( String( hex || '' ).trim() );
	if ( ! match ) {
		return null;
	}
	let value = match[ 1 ];
	if ( value.length === 3 ) {
		value = value
			.split( '' )
			.map( ( c ) => c + c )
			.join( '' );
	}
	const n = parseInt( value, 16 );
	return [ ( n >> 16 ) & 255, ( n >> 8 ) & 255, n & 255 ];
};

const channel = ( v ) => {
	const s = v / 255;
	return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 );
};

const luminance = ( rgb ) =>
	0.2126 * channel( rgb[ 0 ] ) +
	0.7152 * channel( rgb[ 1 ] ) +
	0.0722 * channel( rgb[ 2 ] );

/**
 * Contrast ratio between two hex colors, or null if either is invalid.
 *
 * @param {string} a Hex color.
 * @param {string} b Hex color.
 * @return {number|null} Ratio from 1 to 21.
 */
export const contrastRatio = ( a, b ) => {
	const rgbA = toRgb( a );
	const rgbB = toRgb( b );
	if ( ! rgbA || ! rgbB ) {
		return null;
	}
	const la = luminance( rgbA );
	const lb = luminance( rgbB );
	const [ hi, lo ] = la >= lb ? [ la, lb ] : [ lb, la ];
	return ( hi + 0.05 ) / ( lo + 0.05 );
};

export const AA_RATIO = 4.5;

/**
 * @param {string} fg Foreground hex.
 * @param {string} bg Background hex.
 * @return {{ratio: number|null, passes: boolean}} Result.
 */
export const checkContrast = ( fg, bg ) => {
	const ratio = contrastRatio( fg, bg );
	return { ratio, passes: ratio !== null && ratio >= AA_RATIO };
};
