import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const BASE = '/gpi/v1';

export function get( path, query = {} ) {
	return apiFetch( { path: addQueryArgs( BASE + path, query ) } );
}

export function post( path, data = {} ) {
	return apiFetch( { path: BASE + path, method: 'POST', data } );
}

export function upload( path, formData ) {
	return apiFetch( { path: BASE + path, method: 'POST', body: formData } );
}

export function errorMessage( error ) {
	if ( error && error.message ) {
		return error.message;
	}
	return String( error );
}
