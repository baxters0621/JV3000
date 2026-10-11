const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/modules/salidas/salidas.js', 'utf8');
const helperMatch = source.match(
    /function productoYaAgregadoEnVenta\(productos, grupo, idProducto\) \{[\s\S]*?\n\s*\}/
);

assert.ok(helperMatch, 'Debe existir el predicado puro de duplicado para VENTA.');

const productoYaAgregadoEnVenta = vm.runInNewContext(`(${helperMatch[0]})`);

assert.equal(productoYaAgregadoEnVenta([{ id_producto: 12 }], 'venta', 12), true);
assert.equal(productoYaAgregadoEnVenta([{ id_producto: 12 }], 'venta', 13), false);
assert.equal(productoYaAgregadoEnVenta([{ id_producto: 12 }], 'merma', 12), false);
assert.equal(productoYaAgregadoEnVenta([{ id_producto: 12 }], 'regalias', 12), false);

const duplicateGuard = source.indexOf('if (productoYaAgregadoEnVenta(');
const addLine = source.indexOf('selectedProducts.push({', duplicateGuard);
assert.ok(duplicateGuard >= 0 && addLine > duplicateGuard, 'El duplicado debe bloquearse antes de agregar la línea.');
assert.match(source, /La venta admite una sola línea por producto\..*Elimine la línea existente y vuelva a agregarlo con la cantidad total\./);

console.log('OK duplicados bloqueados solo en VENTA antes de agregar; productos distintos y otros grupos conservados');
