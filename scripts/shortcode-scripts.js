EventPayGate = function(allowCart, maxTickets, soldOutText) {
	this.table = document.getElementById("paygate-cart");
	this.form = document.getElementById("paygate-form");
	this.nameField = document.getElementById("paygate-ticket-name");
	this.totalField = document.getElementById("paygate-total");
	this.checkoutButton = document.getElementById('paygate-checkout');
	this.allowMultiple = !!allowCart;
	this.total = 0;
	this.checkoutButton.setAttribute('disabled','disabled');
	this.maxTickets = maxTickets;
	
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
	
	this.addTicket = function(button, type) {
		if (!button || !type) return false;
		if (this.nameField && !this.nameField.value)
			return alert("יש למלא שם של מחזיק הכרטיס");
		
		var price = parseFloat(window.paygate_ticket_types[type][this.total == 0 ? 0 : 1]);
		if (this.allowMultiple) {
			var ticket = document.createElement('tr');
			ticket.appendChild(this.makeCell(type));
			ticket.appendChild(this.makeCell(price));
			ticket.appendChild(this.makeCell(this.nameField.value));
			this.table.tBodies[0].appendChild(ticket);
		}
		this.addTicketField(type, price, this.nameField ? this.nameField.value: '');
		this.total += price;
		this.totalField.innerHTML = this.total;
		if (!this.allowMultiple)
			return this.form.submit();
		this.updateTicketPrices();
		if (this.maxTickets > 0)
			this.maxTickets-=1;
		if (this.maxTickets == 0 && soldOutText) {
			button.disabled = true;
			button.innerHTML = soldOutText;
		}
	};
	
	this.addTicketField = function(type, price, name) {
		let customFields = {};
		document.querySelectorAll('[name^=paygate-field-]').forEach(el => customFields[el.name.replace(/paygate-field-/,'')] = el.value);
		var input = document.createElement('input');
		input.setAttribute('type','hidden');
		input.setAttribute('name','tickets[' + type + '][]');
		input.setAttribute('value', price + ';' + name + ';' + btoa(encodeURIComponent(JSON.stringify(customFields))));
		this.form.appendChild(input);
		this.checkoutButton.removeAttribute('disabled');
	};
	
	this.makeCell = function(text) {
		var td = document.createElement('td');
		td.appendChild(document.createTextNode(text));
		return td;
	};
	
	this.updateTicketPrices();
	
	return this;
}
