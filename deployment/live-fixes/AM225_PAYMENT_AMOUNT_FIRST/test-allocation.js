'use strict';
const assert=require('node:assert/strict');
const {allocateOldestFirst,paisa}=require('./payment-first.js');
const bills=[
 {key:'bill-latest',bill_date:'2026-10-30',outstanding:4000},
 {key:'bill-oldest',bill_date:'2026-10-01',outstanding:2000},
 {key:'bill-middle',bill_date:'2026-10-10',outstanding:3000}
];
function byKey(result){return Object.fromEntries(result.items.map(x=>[x.key,x.allocated]));}
let r=allocateOldestFirst('5000.00',bills);
assert.equal(r.count,2);assert.equal(r.allocated,500000);assert.equal(r.unallocated,0);
assert.deepEqual(byKey(r),{'bill-oldest':200000,'bill-middle':300000,'bill-latest':0});
r=allocateOldestFirst('3500.00',bills);
assert.equal(r.count,2);assert.equal(r.allocated,350000);assert.equal(r.unallocated,0);
assert.deepEqual(byKey(r),{'bill-oldest':200000,'bill-middle':150000,'bill-latest':0});
r=allocateOldestFirst('12000',bills);
assert.equal(r.count,3);assert.equal(r.allocated,900000);assert.equal(r.unallocated,300000);
r=allocateOldestFirst('400',[{key:'osl-47',bill_date:'2026-10-30',outstanding:400}]);
assert.equal(r.count,1);assert.equal(r.allocated,40000);assert.equal(r.unallocated,0);
r=allocateOldestFirst('0',bills);assert.equal(r.count,0);assert.equal(r.unallocated,0);
assert.equal(paisa('0.01'),1);
console.log('AM225_PAYMENT_FIRST_ALLOCATION_TESTS_PASS 5 scenarios');
