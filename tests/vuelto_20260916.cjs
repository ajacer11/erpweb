const fs = require('fs');
const assert = require('assert');
const vm = require('vm');
const source = fs.readFileSync('newfactura.js','utf8');
const start = source.indexOf('    async function grabarInternal() {');
const end = source.indexOf('        const localCheck =',start);
const body = source.slice(start,end) + 'return {imp1Num, imp2Num}; }';
async function caso(i1,i2,total,seleccion,confirmado=true,m2='2') {
 const values = {'#pay_i1':String(i1),'#pay_i2':String(i2),'#pay_m1':'1','#pay_m2':m2};
 const dialogs=[];
 const context={cart:[{totalFinal:total}],globalAdj:{desc:0,reca:0},calculateLineData:x=>x,r:x=>Math.round(x*100)/100,normImporte:x=>Number(String(x).replace(',','.')),actualizarBalancePago:()=>{},
 $:selector=>({val:value=>value===undefined?values[selector]:(values[selector]=value),text:()=>selector.includes('m1')?'Efectivo Puesto 3':'Tarjeta'}),
 Swal:{fire:async (...args)=>{dialogs.push(args);return {isConfirmed:confirmado,value:seleccion};}}};
 vm.createContext(context);
 vm.runInContext(body+'; globalThis.run=grabarInternal;',context);
 const result = await context.run();
 return {result,values,dialogs};
}
(async()=>{
 let c=await caso(100,50,120,'1');assert.equal(c.result.imp1Num,70);assert.equal(c.result.imp2Num,50);assert.match(c.dialogs[0][0].inputOptions['1'],/Efectivo Puesto 3/);
 c=await caso(100,50,120,'2');assert.equal(c.result.imp2Num,20);
 c=await caso(100,50,50,'1');assert.equal(c.result.imp1Num,0);
 c=await caso(100,50,120,'1',false);assert.equal(c.values['#pay_i1'],'100');
 c=await caso(100,100,50,'1');assert.equal(c.values['#pay_i1'],'100');assert.equal(c.dialogs[0][0],'Atención');
 c=await caso(120,0,120,'1');assert.equal(c.dialogs.length,0);
 c=await caso(-1,0,120,'1');assert.equal(c.dialogs[0][0],'Atención');
 c=await caso(10.10,10.20,20.25,'2');assert.equal(c.result.imp2Num,10.15);
 console.log('OK: vuelto en ambos medios, cero, cancelación, exceso no absorbible, sin vuelto, negativos y centavos.');
})().catch(e=>{console.error(e);process.exitCode=1;});
