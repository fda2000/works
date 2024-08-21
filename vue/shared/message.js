const Sound = require('./sound.js');

function Message(main, delay = 300000) {
	this.map = {
		error: {
			title: 'Ошибка',
			variant: 'danger',
			sound: 'error'
		},
		warning: {
			title: 'Предупреждение',
			variant: 'danger',
			sound: 'warning'
		},
		success: {
			title: 'Успешно',
			variant: 'success',
			sound: 'ok'
		},
		ok: {
			title: 'Успешно',
			variant: 'success',
			sound: 'ok'
		},
		info: {
			title: 'Информация',
			variant: 'info',
			sound: 'ok'
		}
	}

	this.delay = delay;

	/**
	 * @deprecated
	 * **/
  this.triggerError =  async function (message, sound= true) {
		return new Message(main).display('error', message, sound);
  }

	this.showWithLinks =  async function (href, message, sound= false) {
    main.$bvToast.toast(formatMessageWithLinks(href, message), {
      title: 'Информация',
      variant: 'info',
      autoHideDelay: 300000,
    });
		if (sound) {
      await new Sound().error();
    }
  }

	this.info = async function (message, sound = false) {
		return new Message(main, 6000).display('info', message, sound);
	}

	this.ok = async function (message, sound = false) {
		return new Message(main, 6000).display('success', message, sound);
	}

	this.success = async function (message, sound = false) {
		return new Message(main, 6000).display('success', message, sound);
	}

	this.error = async function (message, sound = false) {
		return new Message(main).display('error', message, sound);
	}

	this.warning = async function (message, sound = false) {
		return new Message(main).display('warning', message, sound);
	}


	/**
	 * @deprecated
	 * **/
  this.messageInfo = async function (message, sound = false) {
		return new Message(main, 6000).display('info', message, sound);
  }

	/**
	 * @deprecated
	 * **/
	this.show = async function (message, mood = false) {
		if (!message) {
			return;
		}
		if (mood) {
			await this.info(message);
		} else {
			await this.error(message);
		}
	}

	this.display = async function (type, message, sound = false) {
		if (!message) {
			return;
		}
		let options = this.map[type];
		options.autoHideDelay = this.delay;
		main.$bvToast.toast(formatMessage(message), options);
		if (sound && options.sound) {
			await new Sound().play(options.sound);
		}
	}

	const formatMessage = function (message) {
		if (message.constructor.name === 'Array') {
			message = message.map((str) => main.$createElement('p', str))
		}

		return message
	}

	const formatMessageWithLinks = function (href, message) {
		return main.$createElement('a', {
			attrs: {
				href: href,
				target: '_blank',
			}
		}, message)
	}
}

module.exports = Message;
