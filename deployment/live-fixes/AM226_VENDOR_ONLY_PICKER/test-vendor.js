'use strict';
const assert=require('node:assert/strict');
const {filterVendorNames,allocateOldestFirst}=require('./vendor-only.js');
const names=[
 ['10','SAHERA ENGINEERING WORKSHOP (DAKHINGAON)'],
 ['20','S.K. ENGINEERING WORKS'],
 ['30','MRV AUTOMATES']
];
assert.deepEqual(filterVendorNames(names,'s.k').map(x=>x[0]),['20']);
assert.deepEqual(filterVendorNames(names,'SAHERA').map(x=>x[0]),['10']);
assert.deepEqual(filterVendorNames(names,'engineering works').map(x=>x[0]),['20']);
assert.deepEqual(filterVendorNames(names,'').map(x=>x[0]),['30','20','10']);
assert.deepEqual(filterVendorNames(names,'OB-261030184631-517'),[]);
assert.deepEqual(filterVendorNames(names,'₹2000'),[]);
assert.equal(allocateOldestFirst('2500',[{key:'OSL_JOB::47',outstanding:2000,bill_date:'2026-10-20'},{key:'OSL_JOB::49',outstanding:2000,bill_date:'2026-10-21'}]).count,2);
const fs=require('node:fs');
const content=fs.readFileSync(require('node:path').join(__dirname,'vendor-only.js'),'utf8');
assert.match(content,/searchResults=filterVendorNames\(\[\.\.\.vendorById\.entries\(\)\],search\.value\)/);
assert.match(content,/extraVendorLabel\.style\.display='none'/);
assert.doesNotMatch(content,/\.\.\.bills\.map\(source=>\(\{kind:'bill'/);
console.log('AM226_VENDOR_ONLY_SEARCH_TESTS_PASS 9 checks');
