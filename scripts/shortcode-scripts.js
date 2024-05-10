EventPayGate = function(allowCart, maxTickets, usesRooms) {
	this.table = document.getElementById("paygate-cart");
	this.form = document.getElementById("paygate-form");
	this.nameField = document.getElementById("paygate-ticket-name");
	this.totalField = document.getElementById("paygate-total");
	this.checkoutButton = document.getElementById('paygate-checkout');
	this.allowMultiple = !!allowCart;
	this.total = 0;
	this.checkoutButton.setAttribute('disabled','disabled');
	this.maxTickets = maxTickets;
	this.usesRooms = usesRooms;
	this.messages = {
		'sold-out': 'Sold out',
		'missing-name': "Please enter ticket holder's name",
		'missing-room': "Please select ticket category"
	};
	
	this.updateTicketPrices = function() {
		if (!window.paygate_ticket_types) return;
		if (!window.paygate_price_handlers) return;
		for (var tt in window.paygate_ticket_types) {
			var price = window.paygate_ticket_types[tt][this.total == 0 ? 0 : 1];
			if (!window.paygate_price_handlers[tt]) continue;
			window.paygate_price_handlers[tt].forEach(function(h){
				h(price);
			});
		}
	};
	
	this.addTicket = function(button, type, roomSelector) {
		if (!button || !type) return false;
		if (this.nameField && !this.nameField.value)
			return alert(this.messages['missing-name']);
		if (this.usesRooms && roomSelector && document.getElementById(roomSelector) != null) {
			var roomId = document.getElementById(roomSelector).value;
			var roomName = document.getElementById(roomSelector).options[document.getElementById(roomSelector).selectedIndex].innerText;
			if (roomId == '')
				return alert(this.messages['missing-room']);
			roomId = parseInt(roomId);
		}
		
		var price = parseFloat(window.paygate_ticket_types[type][this.total == 0 ? 0 : 1]);
		let ticketDesc = type;
		let customFields = this.getCustomFields();
		let customFieldDesc = [];
		for (let field in customFields) {
			if (customFields[field])
				customFieldDesc.push(field + ": " + customFields[field]);
		}
		if (customFieldDesc.length)
			ticketDesc += " (" + customFieldDesc.join("; ") + ")";
		if (this.allowMultiple) {
			var ticket = document.createElement('tr');
			ticket.appendChild(this.makeCell(ticketDesc));
			if (this.usesRooms)
				ticket.appendChild(this.makeCell(roomName))
			ticket.appendChild(this.makeCell(price));
			ticket.appendChild(this.makeCell(this.nameField.value));
			this.table.tBodies[0].appendChild(ticket);
		}
		this.addTicketField(type, price, this.nameField ? this.nameField.value: '', roomId || 0);
		this.total += price;
		this.totalField.innerHTML = this.total;
		if (!this.allowMultiple)
			return this.form.submit();
		this.updateTicketPrices();
		if (this.maxTickets > 0)
			this.maxTickets-=1;
		if (this.maxTickets == 0 && this.messages['sold-out']) {
			button.disabled = true;
			button.innerHTML = this.messages['sold-out'];
		}
	};
	
	this.getCustomFields = function() {
		let customFields = {};
		document.querySelectorAll('[name^=paygate-field-]').forEach(el => customFields[el.name.replace(/paygate-field-/,'')] = el.value);
		return customFields;
	}
	
	this.resetCustomFields = function() {
		document.querySelectorAll('[name^=paygate-field-]').forEach(el => { el.value = ''; if (el.checked) el.checked = false; });
	}
	
	this.addTicketField = function(type, price, name, roomId) {
		let customFields = this.getCustomFields();
		let input = document.createElement('input');
		input.setAttribute('type','hidden');
		input.setAttribute('name','tickets[' + type + '][]');
		let value = { price, name, fields: customFields };
		if (this.usesRooms)
			value.roomId = roomId;
		input.setAttribute('value', JSON.stringify(value));
		this.form.appendChild(input);
		this.checkoutButton.removeAttribute('disabled');
		this.resetCustomFields();
	};
	
	this.makeCell = function(text) {
		var td = document.createElement('td');
		td.appendChild(document.createTextNode(text));
		return td;
	};
	
	this.updateTicketPrices();
	
	return this;
}
