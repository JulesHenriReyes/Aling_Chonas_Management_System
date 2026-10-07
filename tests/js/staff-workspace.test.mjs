import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.window=globalThis;
await import('../../public/js/staff-customer-picker.js');
await import('../../public/js/catalog-order.js');
const customers=[{id:1,name:'Maria De la Cruz',phone:'09171234567'},{id:2,name:'Ana Santos',phone:'09179998888'}];

test('customer keyboard selection wraps, enters the active record and clears selection on new search',()=>{
    const picker=window.staffCustomerPicker(customers,'','/customers','csrf');
    picker.move(1);
    assert.equal(picker.activeIndex,0);
    picker.move(-1);
    assert.equal(picker.activeIndex,1);
    picker.selectActive();
    assert.equal(picker.selectedId,'2');
    assert.equal(picker.open,false);
    picker.search='Maria';
    picker.searchChanged();
    assert.equal(picker.selectedId,'');
    picker.move(-1);
    picker.selectActive();
    assert.equal(picker.selectedId,'1');
});

test('unfinished inline customer fields restore without selecting an unknown record',()=>{
    const picker=window.staffCustomerPicker(customers,'999','/customers','csrf',{first_name:'Unsaved',phone_number:'123',search:'Unfinished query',showAdd:'1'});
    picker.init();
    assert.equal(picker.selectedId,'');
    assert.equal(picker.newCustomer.first_name,'Unsaved');
    assert.equal(picker.newCustomer.phone_number,'123');
    assert.equal(picker.showAdd,true);
    assert.equal(picker.snapshot().search,'Unfinished query');
});

test('inline creation failure keeps inputs and identifies each invalid field',async()=>{
    const original=globalThis.fetch;
    globalThis.fetch=async()=>({ok:false,status:422,json:async()=>({errors:{first_name:['Enter a first name.'],phone_number:['Enter a valid phone number.']}})});
    try {
        const picker=window.staffCustomerPicker(customers,'','/customers','csrf');
        picker.newCustomer.phone_number='123';
        await picker.createCustomer();
        assert.equal(picker.newCustomer.phone_number,'123');
        assert.deepEqual(Object.keys(picker.fieldErrors),['first_name','phone_number']);
        assert.equal(picker.selectedId,'');
    } finally {globalThis.fetch=original;}
});

test('editor signature ignores staged photo response changes and tracks design changes',()=>{
    const editor=window.catalogOrder([],[],'/quote',true,'owner-draft-line');
    editor.items=[{product_id:1,package_option_id:1,quantity:1,themes:'Blue',special_request:'',add_ons:[]}];
    const before=editor.editorSignature();
    editor.items[0].staged_images=[{staged_path:'private/photo.png'}];
    assert.equal(editor.editorSignature(),before);
    editor.items[0].themes='Pink';
    assert.notEqual(editor.editorSignature(),before);
});
