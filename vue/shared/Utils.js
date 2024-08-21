const {AxiosResponse} = require("axios");

function Utils(main) {
	this.isEqualObjects = function (x, y) {
		const ok = Object.keys, tx = typeof x, ty = typeof y;
		return x && y && tx === 'object' && tx === ty ? (
			ok(x).length === ok(y).length &&
			ok(x).every(key => this.isEqualObjects(x[key], y[key]))
		) : (x === y);
	}

	this.buildDownloadLink = function (response) {
		if (!response || !response.data || !response.headers || !response.headers['content-type']) {
			return null
		}

		const blob = new Blob([response.data], { type: response.headers['content-type'] });
		const name = response.headers['content-disposition'] && response.headers['content-disposition'].match(/filename=["'](.*)["']/) || null
		const link = document.createElement('a');
		link.href = window.URL.createObjectURL(blob);
		name && name[1] && (link.download = name[1])

		return link
	}

	this.printByUrl = function (url) {
		const printWindow = window.open(url, 'Print')
		printWindow.addEventListener('load', function () {
			printWindow.print()
			!Boolean(printWindow.chrome) && printWindow.close()
		}, false)
	}

	this.getObjectByUrl = function(url) {
		const ret = {}
		url = new URLSearchParams(url)
		url.forEach((value, key) => {
			if (key.substring(key.length - 2) === '[]') {
				key = key.substring(0, key.length - 2)
				if (!ret[key]) {
					ret[key] = []
				}
				ret[key].push(value)
			} else {
				ret[key] = value
			}
		})
		return ret
	}

	this.getUrlByObject = function (obj) {
		const url = new URLSearchParams()

		Object.entries(obj).map(([id, value]) =>
			Array.isArray(value) && value.map((val) => url.append(id + '[]', val)) ||
			(value !== '' && value !== false) && url.append(id, '' + value))

		return url
	}

	this.buildForm = (obj) => {
		const formData = new FormData()

		function add(keyPrev, obj) {
			if (typeof obj !== 'object' || obj instanceof File) {
				formData.append(keyPrev, obj)
				return
			}

			obj && Object.entries(obj).map(([key, value]) => {
				if (obj.hasOwnProperty(key) && value !== null && value !== false) {
					const key1 = keyPrev + (keyPrev ? '[' + key + ']' : key)
					if (Array.isArray(value)) {
						value.map((val) => formData.append(key1 + '[]', val))
					} else {
						add(key1, value)
					}
				}
			})
		}

		add('', obj)
		return formData
	}

	this.recursiveAssign = function( object, ...toassign ){
   if( typeof object === 'object' ){
       toassign.forEach( data => {
           if (isPlainObject(data) ){
               mergeInObject( object, data );
           }
       });
   }
   return object;
	function assign( ref, key, value ){
	    if( isPlainObject(value) ){
	        if( !isPlainObject(ref[key]) ){
	            ref[key] = {};
	        }
	        mergeInObject( ref[key], value );
	    }else{
	        ref[key] = value;
	    }
	}

	function mergeInObject( dest, data ){
	    Object.keys( data ).forEach( key => {
	        assign( dest, key, data[key] );
	    });
	}

	function isPlainObject( o ){
		if (o === undefined) {
			return false;
		}
		if (o === null) {
			return false;
		}
		if (o.constructor === undefined) {
			return false;
		}
		return o.constructor.prototype === Object.prototype;
	}
}

}

module.exports = Utils;
