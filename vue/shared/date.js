Date.prototype.dateToString = function() {
	const date = new Date(this)
	date.setMinutes(date.getMinutes() - date.getTimezoneOffset())
	return date.toISOString().split('T')[0]
}

Date.prototype.getLastDay = function() {
	const endDate = new Date(this)
	endDate.setDate(1)
	endDate.setMonth(endDate.getMonth() + 1)
	endDate.setDate(0)
	return endDate.getDate()
}

/** Аналог PHP date() */
Date.prototype.date = function(format) {
	// Проверяем задан ли второй аргумент, если не задан, используем текущую дату и время, иначе используем дату и время заданную вторым параметром
	// Checking whether the second argument is specified, if isn't, use current date and time, else use the date and time specified by the second argument
	const t = this

	// Создаём пустой массив, в которую будем вносить полученные данные
	// Create an empty array, which will be filled with obtained data
	const arr = [];



	// Функция добавляет ведущий 0 там, где это необходимо, если вы не будете использовать те форматы, в которых есть ведущий 0, то можете смело удалить эту функцию
	// The function adds leading 0 where it is necessary, if you'll not use that formats, where is the leading 0, you can bravely delete this function
	const zero = function (value) {
		return value < 10 ? '0' + value : value;
	};


	let a, b, c, d, e
	for (let i = 0; i < format.length; i++) {

		// Получаем и проверяем каждый символ первого аргумента и помещаем соответствующие тому или иному формату данные в массив
		// Obtaining and checking each character of the first argument and push the appropriate data of particular format into the array
		arr[i] = format.substr(i, 1);
		switch (arr[i]) {

			// === День ===
			// === Day ===

			case 'd':
				// День месяца, 2 цифры с ведущим нулём
				// Day of the month, 2 digits with leading zeros
				arr[i] = zero(t.getDate());
				break;

			case 'D':
				// Текстовое представление дня недели, 3 символа
				// A textual representation of a day, three letters
				const wShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
				arr[i] = wShort[t.getDay()];
				break;

			case 'j':
				// День месяца без ведущего нуля
				// Day of the month without leading zeros
				arr[i] = t.getDate();
				break;

			case 'l':
				// Полное наименование дня недели
				// A full textual representation of the day of the week
				const wFull = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
				arr[i] = wFull[t.getDay()];
				break;

			case 'N':
				// Порядковый номер дня недели в соответствии со стандартом ISO-8601
				// ISO-8601 numeric representation of the day of the week
				arr[i] = t.getDay() || 7;
				break;

			case 'S':
				// Английский суффикс порядкового числительного дня месяца, 2 символа
				// English ordinal suffix for the day of the month, 2 characters
				const S = ['st', 'nd', 'rd', 'th'],
					s = t.getDate() - 1;
				arr[i] = s > 3 ? S[3] : S[s];
				break;

			case 'w':
				// Порядковый номер дня недели
				// Numeric representation of the day of the week
				arr[i] = t.getDay();
				break;

			case 'z':
				// Порядковый номер дня в году (начиная с 0)
				// The day of the year (starting from 0)
				a = t.getFullYear();
				b = new Date(a, t.getMonth(), t.getDate());
				c = new Date(a, 0, 1);
				arr[i] = Math.round((b - c) / 86400000);
				break;

			// === Неделя ===
			// === Week ===

			case 'W':
				// Порядковый номер недели года в соответствии со стандартом ISO-8601; недели начинаются с понедельника
				// ISO-8601 week number of year, weeks starting on Monday
				a = new Date(t.getFullYear(), t.getMonth(), t.getDate() - t.getDay() + 3);
				b = new Date(a.getFullYear(), 0, 4);
				arr[i] = zero(1 + Math.round((a - b) / 86400000 / 7));
				break;

			// === Месяц ===
			// === Month ===

			case 'F':
				// Полное наименование месяца, например January или March
				// A full textual representation of a month, such as January or March
				const mFull = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
				arr[i] = mFull[t.getMonth()];
				break;

			case 'm':
				// Порядковый номер месяца с ведущим нулём
				// Numeric representation of a month, with leading zeros
				arr[i] = zero(t.getMonth() + 1);
				break;

			case 'M':
				// Сокращенное наименование месяца, 3 символа
				// A short textual representation of a month, three letters
				const mShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
				arr[i] = mShort[t.getMonth()];
				break;

			case 'n':
				// Порядковый номер месяца без ведущего нуля
				// Numeric representation of a month, without leading zeros
				arr[i] = t.getMonth() + 1;
				break;

			case 't':
				// Количество дней в указанном месяце
				// Number of days in the given month
				const days = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
				arr[i] = days[t.getMonth()];
				break;

			// === Год ===
			// === Year ===

			case 'L':
				// Признак високосного года
				// Whether it's a leap year
				a = t.getFullYear();
				arr[i] = a % 4 === 0 & a % 100 !== 0 | a % 400 === 0;
				break;

			case 'o':
				// Номер года в соответствии со стандартом ISO-8601. Имеет то же значение, что и Y, кроме случая, когда номер недели ISO (W) принадлежит предыдущему или следующему году; тогда будет использован год этой недели.
				// ISO-8601 year number. This has the same value as Y, except that if the ISO week number (W) belongs to the previous or next year, that year is used instead.
				a = t.getFullYear();
				b = t.getMonth() + 1;
				c = new Date(a, t.getMonth(), t.getDate() - t.getDay() + 3);
				d = new Date(c.getFullYear(), 0, 4);
				e = zero(1 + Math.round((c - d) / 86400000 / 7));
				arr[i] = a + (b === 12 && e < 9 ? 1 : e === 1 && e > 9 ? -1 : 0);
				break;

			case 'Y':
				// Порядковый номер года, 4 цифры
				// A full numeric representation of a year, 4 digits
				arr[i] = t.getFullYear();
				break;

			case 'y':
				// Номер года, 2 цифры
				// A two digit representation of a year
				arr[i] = String(t.getFullYear()).slice(-2);
				break;

			// === Время ===
			// === Time ===

			case 'a':
				// Ante meridiem (англ. "до полудня") или Post meridiem (англ. "после полудня") в нижнем регистре
				// Lowercase Ante meridiem and Post meridiem
				arr[i] = t.getHours() > 11 ? 'pm' : 'am';
				break;

			case 'A':
				// Ante meridiem или Post meridiem в верхнем регистре
				// Uppercase Ante meridiem and Post meridiem
				arr[i] = t.getHours() > 11 ? 'PM' : 'AM';
				break;

			case 'B':
				// Время в формате Интернет-времени (альтернативной системы отсчета времени суток)
				// Swatch Internet time
				a = t.getUTCHours() * 3600;
				b = t.getUTCMinutes() * 60;
				c = t.getUTCSeconds();
				arr[i] = zero(Math.floor((a + b + c + 3600) / 86.4) % 1000, 3);
				break;

			case 'g':
				// Часы в 12-часовом формате без ведущего нуля
				// 12-hour format of an hour without leading zeros
				arr[i] = t.getHours() % 12 || 12;
				break;

			case 'G':
				// Часы в 24-часовом формате без ведущего нуля
				// 24-hour format of an hour without leading zeros
				arr[i] = t.getHours();
				break;

			case 'h':
				// Часы в 12-часовом формате с ведущим нулём
				// 12-hour format of an hour with leading zeros
				arr[i] = zero(t.getHours() % 12 || 12);
				break;

			case 'H':
				// Часы в 24-часовом формате с ведущим нулём
				// 24-hour format of an hour with leading zeros
				arr[i] = zero(t.getHours());
				break;

			case 'i':
				// Минуты с ведущим нулём
				// Minutes with leading zeros
				arr[i] = zero(t.getMinutes());
				break;

			case 's':
				// Секунды с ведущим нулём
				// Seconds, with leading zeros
				arr[i] = zero(t.getSeconds());
				break;

			case 'u':
				// Микросекунды
				// Microseconds
				a = String(t.getMilliseconds() * 1000);
				while (a.length < 6) {
					a = '0' + a;
				}
				arr[i] = a;
				break;

			// === Временная зона ===
			// === Timezone ===

			case 'e':
				// Код шкалы временной зоны, требует очень большой массив функции timezone_abbreviations_list(), так что, если сильно хочется, то придётся чуточку помучаться :)
				// Timezone identifier, requires very large array of the timezone_abbreviations_list() function, so if you highly want, you'll have to a little bit suffer :)
				break;

			case 'I':
				// Признак летнего времени
				// Whether or not the date is in daylight saving time
				a = t.getFullYear();
				b = new Date(a, 0);
				c = Date.UTC(a, 0);
				d = new Date(a, 6);
				e = Date.UTC(a, 6);
				arr[i] = ((b - c) !== (d - e)) ? 1 : 0;
				break;

			case 'O':
				// Разница с временем по Гринвичу, в часах
				// Difference to Greenwich time (GMT) in hours
				a = t.getTimezoneOffset();
				b = Math.abs(a);
				c = String(Math.floor(b / 60) * 100 + b % 60, 4);
				while (c.length < 4) {
					c = '0' + c;
				}
				arr[i] = (a > 0 ? '-' : '+') + c;
				break;

			case 'P':
				// Разница с временем по Гринвичу с двоеточием между часами и минутами
				// Difference to Greenwich time (GMT) with colon between hours and minutes
				a = t.getTimezoneOffset();
				b = Math.abs(a);
				c = String(Math.floor(b / 60) * 100 + b % 60, 4);
				while (c.length < 4) {
					c = '0' + c;
				}
				d = (a > 0 ? '-' : '+') + c;
				arr[i] = d.substr(0, 3) + ':' + d.substr(3, 2);
				break;

			case 'T':
				// Аббревиатура временной зоны, смотри case 'e'
				// Timezone abbreviation, look case 'e'
				arr[i] = 'UTC';
				break;

			case 'Z':
				// Смещение временной зоны в секундах. Для временных зон, расположенных западнее UTC возвращаются отрицательные числа, а расположенных восточнее UTC - положительные.
				// Timezone offset in seconds. The offset for timezones west of UTC is always negative, and for those east of UTC is always positive.
				arr[i] = -t.getTimezoneOffset() * 60;
				break;

			// === Полная дата/время ===
			// === Full Date/Time ===
			case 'c':
				// Дата в формате стандарта ISO 8601
				// ISO 8601 date
				a = t.getTimezoneOffset();
				b = Math.abs(a);
				c = String(Math.floor(b / 60) * 100 + b % 60, 4);
				while (c.length < 4) {
					c = '0' + c;
				}
				d = (a > 0 ? '-' : '+') + c;
				e = d.substr(0, 3) + ':' + d.substr(3, 2);
				arr[i] = t.getFullYear() + '-' + zero(t.getMonth() + 1) + '-' + zero(t.getDate()) + 'T' + zero(t.getHours()) + ':' + zero(t.getMinutes()) + ':' + zero(t.getSeconds()) + e;
				break;

			case 'r':
				// Дата в формате » RFC 2822
				// » RFC 2822 formatted date
				const w = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
				a = w[t.getDay()];
				const m = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
				b = m[t.getMonth()];
				c = t.getTimezoneOffset();
				d = Math.abs(c);
				e = String(Math.floor(d / 60) * 100 + d % 60, 4);
				while (e.length < 4) {
					e = '0' + e;
				}
				const f = (c > 0 ? '-' : '+') + e;
				arr[i] = a + ', ' + zero(t.getDate()) + ' ' + b + ' ' + t.getFullYear() + ' ' + zero(t.getHours()) + ':' + zero(t.getMinutes()) + ':' + zero(t.getSeconds()) + ' ' + f;
				break;

			case 'U':
				// Количество секунд, прошедших с начала Эпохи Unix (The Unix Epoch, 1 января 1970 00:00:00 GMT)
				// Seconds since the Unix Epoch (January 1 1970 00:00:00 GMT)
				arr[i] = t/1000 | 0;
				break;

			default:
				// Остальные символы, в том числе и латинские буквы не упомянутые выше, помещаются в массив как есть
				// Other characters, including the latin simbols which are not mentioned above, are pushed into the array as they are
				arr[i];
				break;
		}
	}



	// Возвращаем заполненный данными массив
	// Return the array filled with obtained data
	return arr.join("");
}
