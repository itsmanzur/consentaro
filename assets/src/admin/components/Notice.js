import { Notice as WPNotice } from '@wordpress/components';

const Notice = ( { status, onRemove, children } ) => (
	<WPNotice status={ status } isDismissible onRemove={ onRemove }>
		{ children }
	</WPNotice>
);

export default Notice;
