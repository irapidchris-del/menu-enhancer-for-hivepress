/**
 * QA harness for the settings preview's key construction and order merge.
 *
 * These are the two places where a wrong answer is SILENT: the settings screen
 * looks right, the owner presses Save, and the real account menu comes back in
 * an order nobody asked for. The 3.3.1 drag bug was exactly this - the key
 * builder identified a custom item by its row POSITION while the server
 * identifies it by its stored uid, so an item dragged to the top of the preview
 * was written to the bottom of the menu.
 *
 * The validators are here too: they decide whether a half-typed colour paints
 * or clears, which is small but is also invisible until somebody notices the
 * preview is lying.
 *
 * Run the whole matrix with: node tests/run-js.js
 *
 * @package AccountMenuEnhancer\Tests
 */

'use strict';

const { logic } = require( './load-logic' );
const { ok, is, finish, section } = require( './harness' );

process.stdout.write( '=== Account Menu Enhancer preview logic: keys, order merge, validators ===\n' );

/* ===================== A. the custom item key ===================== */
section( '[A] customItemKey - what the SERVER will know this item by' );

is( logic.customItemKey( 'ab12cd34', 0 ), 'amehp_item_ab12cd34', 'A1 a row with a stored id is keyed by that id' );
is( logic.customItemKey( 'ab12cd34', 7 ), 'amehp_item_ab12cd34', 'A2 and its row position makes no difference to the key' );
is( logic.customItemKey( '', 0 ), 'amehp_item_1', 'A3 a row with no id falls back to its position, counting from one' );
is( logic.customItemKey( '', 2 ), 'amehp_item_3', 'A4 the positional fallback is the row number, not the array index' );
is( logic.customItemKey( undefined, 0 ), 'amehp_item_1', 'A5 a missing id field is the same as an empty one' );
is( logic.customItemKey( 'abc12', 0 ), 'amehp_item_1', 'A6 an id under six characters is not an id, so the position is used' );
is( logic.customItemKey( 'abc123', 0 ), 'amehp_item_abc123', 'A7 six characters is the shortest id accepted' );
is( logic.customItemKey( 'a'.repeat( 32 ), 0 ), 'amehp_item_' + 'a'.repeat( 32 ), 'A8 thirty-two characters is the longest id accepted' );
is( logic.customItemKey( 'a'.repeat( 33 ), 0 ), 'amehp_item_1', 'A9 anything longer is refused and the position is used' );
is( logic.customItemKey( 'ab12-cd34', 0 ), 'amehp_item_1', 'A10 a hyphen is not allowed in an id' );
is( logic.customItemKey( 'ab12_cd34', 0 ), 'amehp_item_1', 'A11 nor is an underscore' );
is( logic.customItemKey( 'AB12CD34', 0 ), 'amehp_item_AB12CD34', 'A12 an id is case sensitive and upper case is kept as it is' );

// The regression itself. This is the one check that would have caught 3.3.1.
ok( 'amehp_item_3' !== logic.customItemKey( 'ab12cd34', 2 ), 'A13 a row that HAS an id is never keyed positionally - the 3.3.1 drag bug' );

/* ===================== B. the keys on the screen ===================== */
section( '[B] customItemKeys - every custom key the screen is showing' );

is(
	logic.customItemKeys( [ { uid: 'ab12cd34', label: 'Blog' }, { uid: 'ef56gh78', label: 'Help' } ] ),
	[ 'amehp_item_ab12cd34', 'amehp_item_ef56gh78' ],
	'B1 two saved rows give their two id keys, in row order'
);

is( logic.customItemKeys( [] ), [], 'B2 no rows means no keys' );
is( logic.customItemKeys( undefined ), [], 'B3 and a missing row list is not an error' );

is(
	logic.customItemKeys( [ { uid: 'ab12cd34', label: '' }, { uid: 'ef56gh78', label: 'Help' } ] ),
	[ 'amehp_item_ef56gh78' ],
	'B4 a row with no label is skipped, because get_custom_items() skips it too'
);

// An unlabelled row still occupies a position. get_custom_items() counts the
// stored rows, so the row BELOW an unlabelled one must keep the position it
// would have had, or the positional fallback hands out a key the server never
// wrote.
is(
	logic.customItemKeys( [ { uid: '', label: '' }, { uid: '', label: 'Help' } ] ),
	[ 'amehp_item_2' ],
	'B5 an unlabelled row is skipped but still counts towards the positions below it'
);

is(
	logic.customItemKeys( [ { uid: 'ab12cd34', label: 'Blog' }, { uid: '', label: 'Legacy' } ] ),
	[ 'amehp_item_ab12cd34', 'amehp_item_2' ],
	'B6 an id row and a pre-migration row can sit side by side, each keyed its own way'
);

/* ===================== C. reading the stored order ===================== */
section( '[C] parseOrder - the hidden field into a list' );

is( logic.parseOrder( '' ), [], 'C1 an empty field is no arrangement' );
is( logic.parseOrder( undefined ), [], 'C2 and so is a field that is not there' );
is( logic.parseOrder( 'hp:a,hp:b' ), [ 'hp:a', 'hp:b' ], 'C3 a two-key list reads back as two keys' );
is( logic.parseOrder( 'hp:a,,hp:b' ), [ 'hp:a', 'hp:b' ], 'C4 an empty slot between two separators is dropped' );
is( logic.parseOrder( ',' ), [], 'C5 a list of nothing but separators is no arrangement' );
is( logic.parseOrder( 'hp:a' ), [ 'hp:a' ], 'C6 a single key needs no separator' );

/* ===================== D. the order merge ===================== */
section( '[D] mergeOrder - a drag of the VISIBLE items, into the stored order' );

is(
	logic.mergeOrder( [], [ 'hp:a', 'hp:b' ], [] ),
	[ 'hp:a', 'hp:b' ],
	'D1 with nothing stored, the visible order is the whole arrangement'
);

is(
	logic.mergeOrder( [ 'hp:a', 'hp:b' ], [ 'hp:b', 'hp:a' ], [] ),
	[ 'hp:b', 'hp:a' ],
	'D2 with everything visible, a drag is written straight through'
);

// The off-screen cases. An item can be missing from the preview for reasons
// that have nothing to do with where it belongs, and its place has to survive.
is(
	logic.mergeOrder(
		[ 'hp:listings_edit', 'hp:messages', 'hp:settings' ],
		[ 'hp:settings', 'hp:listings_edit' ],
		[]
	),
	[ 'hp:settings', 'hp:messages', 'hp:listings_edit' ],
	'D3 an item the owner has HIDDEN keeps its slot while the visible ones move around it'
);

is(
	logic.mergeOrder(
		[ 'hp:listings_edit', 'hp:messages', 'wc:orders', 'amehp_item_ab12cd34', 'hp:settings' ],
		[ 'hp:settings', 'hp:listings_edit', 'hp:messages', 'amehp_item_ab12cd34' ],
		[ 'amehp_item_ab12cd34' ]
	),
	[ 'hp:settings', 'hp:listings_edit', 'wc:orders', 'hp:messages', 'amehp_item_ab12cd34' ],
	'D4 a WooCommerce row, off screen because the menus are separate, keeps its slot too'
);

is(
	logic.mergeOrder( [ 'hp:a', 'wc:downloads', 'hp:gone' ], [ 'hp:a' ], [] ),
	[ 'hp:a', 'wc:downloads', 'hp:gone' ],
	'D5 a NON-custom key that matches nothing on screen is kept, never dropped'
);

is(
	logic.mergeOrder( [ 'hp:a' ], [ 'hp:a', 'hp:new' ], [] ),
	[ 'hp:a', 'hp:new' ],
	'D6 an item that is newly visible is appended rather than losing the others their places'
);

is(
	logic.mergeOrder( [], [ 'hp:new' ], [] ),
	[ 'hp:new' ],
	'D7 the first item ever seen is appended to an empty arrangement'
);

// Dropping dead custom keys. Every custom item is on this screen, so one that
// matches no row is deleted or is a positional leftover from before 3.3.2.
is(
	logic.mergeOrder( [ 'hp:a', 'amehp_item_2', 'hp:b' ], [ 'hp:a', 'hp:b' ], [ 'amehp_item_ab12cd34' ] ),
	[ 'hp:a', 'hp:b' ],
	'D8 a stale custom key matching no row on screen is dropped'
);

is(
	logic.mergeOrder( [ 'hp:a', 'amehp_item_ab12cd34', 'hp:b' ], [ 'hp:a', 'hp:b' ], [ 'amehp_item_ab12cd34' ] ),
	[ 'hp:a', 'amehp_item_ab12cd34', 'hp:b' ],
	'D9 but a custom key that DOES match a row keeps its slot, because the row is merely hidden'
);

is(
	logic.mergeOrder( [ 'amehp_item_1', 'amehp_item_2', 'amehp_item_3' ], [], [ 'amehp_item_ab12cd34' ] ),
	[],
	'D10 a whole run of pre-3.3.2 positional keys is cleared out rather than accumulating'
);

is(
	logic.mergeOrder( [ 'hp:a', 'hp:a' ], [ 'hp:a' ], [] ),
	[ 'hp:a' ],
	'D11 a key repeated in the stored order does not produce a repeated key out'
);

is(
	logic.mergeOrder( [ 'hp:a', 'hp:b' ], [ 'hp:a' ], [] ),
	[ 'hp:a', 'hp:b' ],
	'D12 a stored key that has since been hidden is kept even when the visible list is shorter'
);

// The whole chain, exactly as the screen runs it: build the keys from the rows,
// treat them as what the preview is showing, merge into what the SERVER wrote.
// This is the check the 3.3.1 bug would have failed.
const draggedRows = [ { uid: 'ab12cd34', label: 'Blog' } ];
const draggedKeys = logic.customItemKeys( draggedRows );

is(
	logic.mergeOrder(
		[ 'hp:listings_edit', 'amehp_item_ab12cd34' ],
		[ logic.customItemKey( draggedRows[ 0 ].uid, 0 ), 'hp:listings_edit' ],
		draggedKeys
	),
	[ 'amehp_item_ab12cd34', 'hp:listings_edit' ],
	'D13 a custom item dragged to the TOP is written to the top of the stored order - the 3.3.1 bug end to end'
);

/* ===================== E. the validators ===================== */
section( '[E] the small validators' );

is( logic.hex( '#fff' ), '#fff', 'E1 a three-digit colour is accepted' );
is( logic.hex( '#ffffff' ), '#ffffff', 'E2 and a six-digit one' );
is( logic.hex( '#FFAA00' ), '#FFAA00', 'E3 case does not matter and the value is returned unchanged' );
is( logic.hex( '#ff' ), '', 'E4 a half-typed colour clears rather than paints' );
is( logic.hex( '#fffff' ), '', 'E5 five digits is not a colour' );
is( logic.hex( 'ffffff' ), '', 'E6 nor is one with no hash' );
is( logic.hex( '#gggggg' ), '', 'E7 nor one with a letter that is not a hex digit' );
is( logic.hex( '' ), '', 'E8 an empty field is no colour' );
is( logic.hex( '#fff000000' ), '', 'E9 and nothing longer than six digits is accepted' );

is( logic.iconName( 'user' ), 'user', 'E10 a plain icon name is accepted' );
is( logic.iconName( 'sign-out-alt' ), 'sign-out-alt', 'E11 hyphens are part of a name' );
is( logic.iconName( '  user  ' ), 'user', 'E12 surrounding space is trimmed off' );
is( logic.iconName( 'User' ), '', 'E13 an upper-case letter is refused, because the class name is lower case' );
is( logic.iconName( 'fa user' ), '', 'E14 a space inside a name is refused' );
is( logic.iconName( 'user"' ), '', 'E15 a quote is refused, so nothing can break out of the class attribute' );
is( logic.iconName( '' ), '', 'E16 an empty field is no icon' );
is( logic.iconName( undefined ), '', 'E17 and a missing field is not an error' );

is( logic.absint( '12' ), 12, 'E18 a number reads as itself' );
is( logic.absint( '-12' ), 12, 'E19 a negative number reads as its absolute value' );
is( logic.absint( 'abc' ), 0, 'E20 text reads as zero' );
is( logic.absint( '' ), 0, 'E21 and so does an empty field' );

is( logic.menuWeight( '700' ), '700', 'E22 a weight in the hundreds is accepted' );
is( logic.menuWeight( '100' ), '100', 'E23 one hundred is the lightest accepted' );
is( logic.menuWeight( '000' ), '', 'E24 zero is not a weight' );
is( logic.menuWeight( '70' ), '', 'E25 nor is a two-digit number' );
is( logic.menuWeight( 'bold' ), '', 'E26 nor a keyword' );
is( logic.menuWeight( '' ), '', 'E27 an empty field leaves the theme its own weight' );

is( logic.iconSize( '' ), 0, 'E28 no size set leaves the theme its own' );
is( logic.iconSize( '0' ), 0, 'E29 and zero is the same as none' );
is( logic.iconSize( '30' ), 30, 'E30 a size inside the range is used as it is' );
is( logic.iconSize( '5' ), 8, 'E31 anything under eight pixels is raised to eight' );
is( logic.iconSize( '900' ), 48, 'E32 anything over forty-eight is capped there' );

is( logic.iconSpacing( '' ), null, 'E33 no spacing set writes no margin at all' );
is( logic.iconSpacing( '0' ), 0, 'E34 but zero is a deliberate no gap and IS written' );
is( logic.iconSpacing( '2' ), 2, 'E35 a spacing inside the range is used as it is' );
is( logic.iconSpacing( '99' ), 60, 'E36 anything over sixty pixels is capped there' );
is( logic.iconSpacing( '-4' ), 4, 'E37 a negative spacing reads as its absolute value' );
is( logic.iconSpacing( 'abc' ), null, 'E38 and text writes no margin' );

is( logic.stroke( 'semibold' ), '0.5px', 'E39 the semibold weight strokes at half a pixel' );
is( logic.stroke( 'bold' ), '1px', 'E40 and bold at one, matching get_stroke_width()' );
is( logic.stroke( '' ), '', 'E41 no weight is no stroke' );
is( logic.stroke( 'regular' ), '', 'E42 a weight with no stroke of its own draws none' );

/* ===================== F. custom item order numbers ===================== */
section( '[F] customItemOrder - the retired Order box, still honoured' );

is( logic.customItemOrder( '', 0 ), 100, 'F1 an item with no stored number sits at 100 plus its row position' );
is( logic.customItemOrder( '', 3 ), 103, 'F2 the fourth such row sits at 103' );
is( logic.customItemOrder( '5', 0 ), 5, 'F3 a number stored by a pre-3.2.0 version is honoured' );
is( logic.customItemOrder( '0', 2 ), 0, 'F4 a stored zero is a real position, not a missing one' );
is( logic.customItemOrder( 'abc', 1 ), 101, 'F5 an unreadable stored value falls back to the row position' );

finish();
