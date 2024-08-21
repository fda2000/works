const Message = require('./message.js');
const Utils = require('./Utils.js');
function ApiClient(main) {
	this.download = function (url, data = null) {
		const form = document.createElement('form')
		form.action = url
		form.method = 'post'

		const formData = new Utils().buildForm(data)
		for (const [name, value] of formData.entries()) {
			if (typeof value !== 'string') {
				continue
			}

			const input = document.createElement('input')
			input.setAttribute('name', name)
			input.setAttribute('value', value)
			form.appendChild(input)
		}

		document.body.appendChild(form)
		form.submit()
		document.body.removeChild(form)
	}

  this.send = async function (url, data=null, config=null, sound = true) {
		const blockerKey = url + JSON.stringify(data)
		if (main.waitBlocker) {
			return false
		}
		if (main.waitBlocker === false) {
			main.waitBlocker = blockerKey
		}
    //const qs = require('qs');
    //data = data&&!data.size? qs.stringify(data) : data;//to simple form, no JSON

    let form = new Utils().buildForm(data);

    let response = {
      data: {
        status: 0,
        message: null
      }
    };
    try {
      response = await main.$axios.post(url, form, config);
    } catch (e) {
      response.data.message = e.message;
    }

		if (main.waitBlocker === blockerKey) {
			main.waitBlocker = false
		}

    if (!response || !response.data || !response.data.status) {
        await new Message(main).error(response && response.data && response.data.message ?
            response.data.message : 'Ошибка. Попробуйте еще раз позднее либо обратитесь к программистам.',
          sound
        );
      return false;
    }

    return response.data;
  }
}

module.exports = ApiClient;
