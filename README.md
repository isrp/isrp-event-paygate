# ISRP Event Paygatew - שער תשלום

## Installation

1. Search for `isrp-event-paygate` in the Wordpress plugin directory and install from there.

## Setup

Go to Settings -> Paygate and set up your Pelepay login email.

* For testing its probably a good idea to enable "allow test purchases", but don't forget to disable it when the event is ready to be publicized.
* The "shopping cart" feature may or may not be useful to you - play with it and see how you like it.

## Events

To start selling tickets, you have to first setup your event. Each even needs a success landing page that the users will get to when they complete
the purcahsing process. This page may provide more instructions on how to get to the event and what to bring, or may just say "Thank you for your purchase" -
this is completely up to you, but you must create at least one such page. If you are running multiple events, they may use the same success landing page - 
again, this is up to you.

1. Go to "Pages", and create a success landing page. Name it watever you want and put whatever content you want the users to see after the purchase.
2. Click "Paygate" in the sidebar, and in the main page type the name of your event, select your success landing page from the list and click "Create".
   Your event should now appear in the table.
3. Under "Periods", add the different pricing periods you want to have - you must have at least one which will terminate with the date of the event, but you
   can add more if you want to offer early-bird pricing. For each period, set the end date of the period, while the start date will automatically be set to the
   the end date of the previous period. The first period has no start date - it is active immediately.

## Tickets

After creating the event and setting the ticket sell periods, you need to set ticket prices. In the event table, click the "payout icon" (A hand with a dollar
sign) or click "Prices" in the sidebar and select your event from the drop down.

The ticket table should already list the default "standard ticket", and you can add different kinds of tickets if your event has more than one ticket type.
For each ticket type you can enter prices for each of the ticket sell periods you've set up for your event - both a regular prices and a club discount price.

Notes:
* If you don't want to offer club discount for a ticket type at a ticket sell period, just clear the price (by clicking the X icon near the price box) and it can
  only be bought for the regular prices.
* If you clear the regular price box for a ticket in a period, that ticket type cannot be sold at all during the specific period.

## Shortcodes

To build the event ticket page, use Wordpress shortcuts as defined below. The ticket page must have the `[paygate-checkout]` shortcode somewhere in the page,
as this shortcode is respondible for the purchase logic. If you have enabled the "shopping cart" feature, then the `[paygate-checkout]` tag will also render
the shopping cart interface.

Additionally, set up a `[paygate-button]` for each ticket type you want to sell. You can use the `[paygate-price]` shortcode to show the ticket prices - this
shortcode automatically calculate the correct price for the current ticket sell period and the club discount, if appropriate.

Finally you can use the `[paygate-name]` shortcode to create a name input field, if you want to require your customers to label their tickets with their name.

### Example

A simple paygate page might look like this:

```
<p>
Name on ticket: [paygate-name]
</p>

 * Standard ticket: [paygate-button]Entry and access to all free-for-all activities, $[paygate-price][/paygate-button]
 * VIP ticket: [paygate-button type="vip"]All activities as well as access to the green room, $[paygate-price][/paygate-button]
 
[paygate-checkout]
```

### `[paygate-checkout]`

Paygate checkout logic. If the "shopping cart" feature is enabled, this will render the shopping cart and checkout button.

#### Attributes

Does not require or support any attribute.

### `[paygate-name]`

Name field for specifying names for named tickets.

#### Attributes

* `width` (optional) width of the text field. Default: automatic.
* `value` (optional) name to pre-set in the field. Default: empty
* `style` (optional) CSS style rule to apply to the name field. Default: none

### `[paygate-button]`

Paygate ticket purchase button.

#### Atrributes

* `type` (optional/required) ticket type of purchase. This attribute is optional if there is only one ticket type, otherwise the ticket type must be specified exactly.

### `[paygate-price]`

Display the ticket price. If used withing the `[paygate-button]` container shortcode, then the `type` field can be left out and the shortcode
will show the price for the ticket of its containing pay button. 

#### Atrributes

* `type` (optional) ticket type to display price for. If this shortcode is contained inside a `[paygate-button]` shortcode, the `type` attribute may be omitted and the ticket type will be taken from
the `[paygate-button]` shortcode. The `type` attribute may also be omitted if there is only one ticket type.

### `[paygate-dragon-form]`

Login form for dragon club members. Use this in a standalone page to allow club members to log in to receive the club discount.

#### Attributes

* `success` (required) page slug to transfer the user after they log in - normally this will be the page where you have set up the other Paygate shortcodes.
* `width` (optional) width of the login field. Default: automatic.
* `style` (optional) CSS style rule to apply to the login field. Default: none.
