document.addEventListener('DOMContentLoaded', () => {
    window.carrito = []; 
    
    const inputBuscador = document.getElementById('buscar-producto');
    const tarjetasProductos = document.querySelectorAll('.tarjeta-producto');
    const listaTicket = document.getElementById('lista-ticket');
    const spanTotalUsd = document.getElementById('total-usd');
    const spanTotalBs = document.getElementById('total-bs');
    
    const radiosPago = document.querySelectorAll('input[name="metodo-pago"]');
    const panelCredito = document.getElementById('panel-credito');
    const spanDeuda = document.getElementById('monto-deuda');
    const btnFacturar = document.querySelector('.btn-facturar');
    const inputCedula = document.getElementById('cliente-cedula');
    const inputNombre = document.getElementById('cliente-nombre');

    const textoTasa = document.getElementById('tasa-actual')?.innerText || '36.50';
    const tasa = parseFloat(textoTasa) || 36.50; 

    const inputBuscadorCliente = document.getElementById('buscador-cliente-pos');
    const cajaSugerencias = document.getElementById('sugerencias-clientes');

    if (inputBuscadorCliente) {
        inputBuscadorCliente.addEventListener('input', async function() {
            let query = this.value.trim();
            if (query.length < 2) { cajaSugerencias.style.display = 'none'; return; }
            try {
                let resp = await fetch('buscar_cliente.php?q=' + encodeURIComponent(query));
                let clientes = await resp.json();
                if (clientes.length > 0) {
                    cajaSugerencias.innerHTML = '';
                    clientes.forEach(c => {
                        let div = document.createElement('div');
                        div.style.padding = '10px'; div.style.borderBottom = '1px solid #eee'; div.style.cursor = 'pointer';
                        div.innerHTML = `<strong>${c.cedula}</strong> - ${c.nombre}`;
                        div.addEventListener('click', () => {
                            document.getElementById('cliente-cedula').value = c.cedula;
                            document.getElementById('cliente-nombre').value = c.nombre;
                            if(document.getElementById('cliente-telefono')) document.getElementById('cliente-telefono').value = c.telefono;
                            document.getElementById('display-cliente-nombre').innerText = c.nombre;
                            document.getElementById('display-cliente-cedula').innerText = c.cedula;
                            document.getElementById('contenedor-buscador-cliente').style.display = 'none';
                            document.getElementById('tarjeta-cliente-seleccionado').style.display = 'flex';
                            inputBuscadorCliente.value = ''; cajaSugerencias.style.display = 'none';
                        });
                        div.addEventListener('mouseover', () => div.style.backgroundColor = '#f1f5f9');
                        div.addEventListener('mouseout', () => div.style.backgroundColor = 'white');
                        cajaSugerencias.appendChild(div);
                    });
                    cajaSugerencias.style.display = 'block';
                } else { cajaSugerencias.style.display = 'none'; }
            } catch (error) { console.error(error); }
        });
        document.addEventListener('click', (e) => { if (e.target !== inputBuscadorCliente && e.target !== cajaSugerencias) cajaSugerencias.style.display = 'none'; });
    }

    if (inputBuscador) {
        inputBuscador.addEventListener('input', (evento) => {
            const textoBusqueda = evento.target.value.toLowerCase().trim();
            tarjetasProductos.forEach(t => {
                if (textoBusqueda === '') { t.style.display = 'none'; return; }
                const nombre = t.getAttribute('data-nombre') || '';
                const codigo = t.getAttribute('data-codigo') || '';
                t.style.display = (nombre.includes(textoBusqueda) || codigo.includes(textoBusqueda)) ? 'block' : 'none';
            });
        });
    }

    document.body.addEventListener('click', (evento) => {
        if (evento.target.classList.contains('btn-agregar')) {
            evento.preventDefault(); evento.stopImmediatePropagation(); 
            const tarjeta = evento.target.closest('.tarjeta-producto');
            const idProducto = tarjeta.getAttribute('data-id');
            const nombre = tarjeta.getAttribute('data-nombre').toUpperCase(); 
            const tipoUnidad = tarjeta.getAttribute('data-unidad');
            const stockDisponible = parseFloat(tarjeta.getAttribute('data-stock')); 
            const selTarifa = document.getElementById('modalidad-tarifa');
            const tarifaActiva = selTarifa ? selTarifa.value : 'bcv_detal';
            
            let precioUsd = 0;
            if (tarifaActiva === 'bcv_detal') precioUsd = parseFloat(tarjeta.getAttribute('data-precio-bcv-detal')) || parseFloat(tarjeta.getAttribute('data-precio'));
            else if (tarifaActiva === 'bcv_mayor') precioUsd = parseFloat(tarjeta.getAttribute('data-precio-bcv-mayor'));
            else if (tarifaActiva === 'efectivo_detal') precioUsd = parseFloat(tarjeta.getAttribute('data-precio-efectivo-detal'));
            else if (tarifaActiva === 'efectivo_mayor') precioUsd = parseFloat(tarjeta.getAttribute('data-precio-efectivo-mayor'));
            if(isNaN(precioUsd) || precioUsd <= 0) precioUsd = parseFloat(tarjeta.getAttribute('data-precio')) || 0;
            
            const inputCantidad = tarjeta.querySelector('.input-cantidad');
            let valorIngresado = inputCantidad.value.trim().replace(',', '.');
            const cantidad = parseFloat(valorIngresado);
            if (isNaN(cantidad) || cantidad <= 0) { alert('⚠️ Ingresa una cantidad válida mayor a cero.'); return; }
            
            const indexExistente = window.carrito.findIndex(item => item.id == idProducto);
            const cantidadEnCarrito = (indexExistente !== -1) ? window.carrito[indexExistente].cantidad : 0;
            if ((cantidad + cantidadEnCarrito) > stockDisponible) { alert('⚠️ Inventario insuficiente.'); return; }

            if (indexExistente !== -1) {
                window.carrito[indexExistente].precioUsd = precioUsd; 
                window.carrito[indexExistente].typePrecio = tarifaActiva;
                window.carrito[indexExistente].cantidad += cantidad; 
            } else {
                window.carrito.push({ id: idProducto, nombre: nombre, precioUsd: precioUsd, tipoUnidad: tipoUnidad, cantidad: cantidad, stock: stockDisponible, typePrecio: tarifaActiva }); 
            }
            inputCantidad.value = (tipoUnidad === 'und') ? '1' : ''; inputBuscador.value = '';
            tarjetasProductos.forEach(t => t.style.display = 'none'); inputBuscador.focus(); window.actualizarTicket(); 
        }

        if (evento.target.closest('.btn-borrar')) {
            evento.preventDefault(); evento.stopImmediatePropagation();
            window.carrito.splice(evento.target.closest('.btn-borrar').getAttribute('data-index'), 1); window.actualizarTicket();
        }

        if (evento.target.closest('.btn-editar')) {
            evento.preventDefault(); evento.stopImmediatePropagation();
            const index = evento.target.closest('.btn-editar').getAttribute('data-index');
            const item = window.carrito[index];
            let indicativo = 'Unidades';
            if (item.tipoUnidad === 'kg') indicativo = 'Kilos (Ej: 0.500)'; if (item.tipoUnidad === 'm') indicativo = 'Metros (Ej: 3.5)';
            let nuevaCantidadStr = prompt(`✏️ Editar cantidad para ${item.nombre}\nIngrese la nueva cantidad en ${indicativo} (Max: ${item.stock}):`, item.cantidad);
            if (nuevaCantidadStr !== null) {
                let nuevaCantidad = parseFloat(nuevaCantidadStr.trim().replace(',', '.'));
                if (!isNaN(nuevaCantidad) && nuevaCantidad > 0) {
                    if (nuevaCantidad > item.stock) { alert(`⚠️ Inventario insuficiente.`); return; }
                    window.carrito[index].cantidad = nuevaCantidad; window.actualizarTicket();
                } else { alert("Cantidad no válida."); }
            }
        }
    });

    document.body.addEventListener('change', (e) => {
        if (e.target.id === 'modalidad-tarifa') {
            const tarifaActiva = e.target.value;
            tarjetasProductos.forEach(t => {
                const id = t.getAttribute('data-id'); let nPrecio = 0;
                if (tarifaActiva === 'bcv_detal') nPrecio = parseFloat(t.getAttribute('data-precio-bcv-detal'));
                else if (tarifaActiva === 'bcv_mayor') nPrecio = parseFloat(t.getAttribute('data-precio-bcv-mayor'));
                else if (tarifaActiva === 'efectivo_detal') nPrecio = parseFloat(t.getAttribute('data-precio-efectivo-detal'));
                else if (tarifaActiva === 'efectivo_mayor') nPrecio = parseFloat(t.getAttribute('data-precio-efectivo-mayor'));
                if(isNaN(nPrecio) || nPrecio <= 0) nPrecio = parseFloat(t.getAttribute('data-precio')) || 0;
                
                const txtUsd = document.getElementById(`render-precio-usd-${id}`);
                const txtBs = document.getElementById(`render-precio-bs-${id}`);
                const uni = t.getAttribute('data-unidad') === 'kg' ? 'Kg' : (t.getAttribute('data-unidad') === 'm' ? 'm' : 'Und');
                
                if(txtUsd) txtUsd.innerText = `$${nPrecio.toFixed(2)} / ${uni}`;
                if(txtBs) txtBs.innerText = `${(nPrecio * tasa).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Bs`;
            });
            
            window.carrito.forEach(item => {
                const tb = document.querySelector(`.tarjeta-producto[data-id="${item.id}"]`);
                if (tb) {
                    let nPrecioItem = 0;
                    if (tarifaActiva === 'bcv_detal') nPrecioItem = parseFloat(tb.getAttribute('data-precio-bcv-detal'));
                    else if (tarifaActiva === 'bcv_mayor') nPrecioItem = parseFloat(tb.getAttribute('data-precio-bcv-mayor'));
                    else if (tarifaActiva === 'efectivo_detal') nPrecioItem = parseFloat(tb.getAttribute('data-precio-efectivo-detal'));
                    else if (tarifaActiva === 'efectivo_mayor') nPrecioItem = parseFloat(tb.getAttribute('data-precio-efectivo-mayor'));
                    if(!isNaN(nPrecioItem) && nPrecioItem > 0) { item.precioUsd = nPrecioItem; item.typePrecio = tarifaActiva; }
                }
            });
            window.actualizarTicket();
        }
    });

    function calcularVueltoGlobal() {
        let spanTotalUsd = document.getElementById('total-usd');
        if (!spanTotalUsd) return;
        
        let totalFacturaUsd = parseFloat(spanTotalUsd.innerText.replace('$', '').trim()) || 0;
        let totalFacturaBs = totalFacturaUsd * tasa;
        
        let metodoSeleccionado = document.querySelector('input[name="metodo-pago"]:checked');
        if (!metodoSeleccionado) return;

        if (metodoSeleccionado.value === 'efectivo_usd') {
            let inputRecibido = document.getElementById('monto-recibido-usd') || document.getElementById('recibido-usd');
            let displayVueltoUsd = document.getElementById('vuelto-usd-display') || document.getElementById('vuelto-total-usd-txt');
            let displayVueltoBs = document.getElementById('vuelto-bs-display') || document.getElementById('vuelto-falta-usd');

            if (inputRecibido && displayVueltoUsd && displayVueltoBs) {
                let recibidoUsd = parseFloat(inputRecibido.value) || 0;
                let vueltoUsd = recibidoUsd - totalFacturaUsd;
                
                if (vueltoUsd > 0 && recibidoUsd > 0) {
                    let vueltoBs = vueltoUsd * tasa;
                    displayVueltoUsd.innerText = '$' + vueltoUsd.toFixed(2);
                    displayVueltoBs.innerText = '(' + vueltoBs.toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' Bs)';
                } else {
                    displayVueltoUsd.innerText = '$0.00';
                    displayVueltoBs.innerText = '(0.00 Bs)';
                }
            }
        }
        
        if (metodoSeleccionado.value === 'efectivo_bs') {
            let recibidoBs = parseFloat(document.getElementById('recibido-bs')?.value) || 0;
            let vueltoTotalBs = recibidoBs - totalFacturaBs;
            
            let displayVueltoBs = document.getElementById('vuelto-total-bs-display');
            let displayFaltaBs = document.getElementById('falta-entregar-bs');
            
            if (vueltoTotalBs > 0 && recibidoBs > 0) {
                if(displayVueltoBs) displayVueltoBs.innerText = vueltoTotalBs.toLocaleString('es-VE', {minimumFractionDigits: 2}) + ' Bs';
                
                let usdEntregados = parseFloat(document.getElementById('vuelto-dado-usd-desde-bs')?.value) || 0;
                let faltaPorEntregarBs = vueltoTotalBs - (usdEntregados * tasa);
                
                if (displayFaltaBs) {
                    if (faltaPorEntregarBs <= 0.1 && faltaPorEntregarBs >= -0.1) {
                        displayFaltaBs.style.color = '#10b981'; 
                        displayFaltaBs.innerText = '0,00 Bs ✅';
                    } else {
                        displayFaltaBs.style.color = '#ef4444'; 
                        displayFaltaBs.innerText = faltaPorEntregarBs.toLocaleString('es-VE', {minimumFractionDigits: 2}) + ' Bs';
                    }
                }
            } else {
                if(displayVueltoBs) displayVueltoBs.innerText = '0,00 Bs';
                if(displayFaltaBs) { displayFaltaBs.innerText = '0,00 Bs'; displayFaltaBs.style.color = '#ef4444'; }
            }
        }
    }

    document.addEventListener('input', (e) => {
        if (e.target.id === 'monto-recibido-usd' || e.target.id === 'recibido-usd' || e.target.id === 'recibido-bs' || e.target.classList.contains('calc-vuelto-bs')) {
            calcularVueltoGlobal();
        }
    });

    window.actualizarTicket = function() {
        if(!listaTicket) return;
        listaTicket.innerHTML = ''; 
        let totalUsd = 0;
        if (window.carrito.length === 0) listaTicket.innerHTML = '<p style="color: #64748b; text-align: center; margin-top: 2rem;">Ningún producto seleccionado</p>';
        
        window.carrito.forEach((item, index) => {
            const subtotalItemUsd = Math.round((item.precioUsd * item.cantidad) * 100) / 100; 
            const subtotalItemBs = subtotalItemUsd * tasa; 
            totalUsd += subtotalItemUsd;
            
            const indicativoUnidad = (item.tipoUnidad === 'kg') ? 'Kg' : (item.tipoUnidad === 'm' ? 'm' : 'Und');
            const html = `<div style="padding-bottom: 0.8rem; margin-bottom: 0.8rem; border-bottom: 1px dashed var(--borde);"><div style="display: flex; justify-content: space-between; align-items: flex-start;"><div><span style="font-weight: bold; color: var(--texto-principal);">${item.nombre}</span><div style="font-size: 0.85rem; color: #64748b;">${item.cantidad} ${indicativoUnidad} x $${item.precioUsd.toFixed(2)}</div></div><div style="text-align: right;"><span style="font-weight: bold;">$${subtotalItemUsd.toFixed(2)}</span><br><span style="color: #10b981; font-weight: bold; font-size: 0.9rem;">${subtotalItemBs.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})} Bs</span></div></div><div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem;"><button class="btn-editar" data-index="${index}" style="background:none; border:none; cursor:pointer;" title="Editar">✏️</button><button class="btn-borrar" data-index="${index}" style="background:none; border:none; cursor:pointer;" title="Eliminar">❌</button></div></div>`;
            listaTicket.insertAdjacentHTML('beforeend', html);
        });
        
        totalUsd = Math.round(totalUsd * 100) / 100;
        const totalBs = Math.round((totalUsd * tasa) * 100) / 100;
        
        if(spanTotalUsd) spanTotalUsd.innerText = `$${totalUsd.toFixed(2)}`; 
        if(spanTotalBs) spanTotalBs.innerText = `${totalBs.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})} Bs`;
        
        if (document.getElementById('panel-pago-mixto') && document.getElementById('panel-pago-mixto').style.display === 'block') {
            calcularPagoMixto();
        }
        calcularDeudaCredito(); 
        
        if (typeof calcularVueltoGlobal === 'function') {
            calcularVueltoGlobal();
        }
    }

    function calcularPagoMixto() {
        const totalCompraUsd = parseFloat((spanTotalUsd.innerText || '0').replace('$', '')) || 0;
        const totalCompraBs = Math.round((totalCompraUsd * tasa) * 100) / 100;

        let mUsd = parseFloat(document.getElementById('mix-usd')?.value) || 0;
        let mEfBs = parseFloat(document.getElementById('mix-efec-bs')?.value) || 0;
        let mPmBs = parseFloat(document.getElementById('mix-pm-bs')?.value) || 0;
        let mPvBs = parseFloat(document.getElementById('mix-pv-bs')?.value) || 0;
        let mBpBs = parseFloat(document.getElementById('mix-bp-bs')?.value) || 0;
        let mOtroUsd = parseFloat(document.getElementById('mix-otro-usd')?.value) || 0;

        let abonadoBs = (mUsd * tasa) + mEfBs + mPmBs + mPvBs + mBpBs + (mOtroUsd * tasa);
        let faltaBs = totalCompraBs - abonadoBs;

        if (document.getElementById('mix-total-pagar')) document.getElementById('mix-total-pagar').innerText = totalCompraBs.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' Bs';
        
        const labelFalta = document.getElementById('mix-falta');
        if (labelFalta) {
            if (faltaBs <= 0.1 && faltaBs >= -0.1) { 
                labelFalta.innerText = '0,00 Bs (Completo ✅)';
                labelFalta.style.color = '#10b981';
            } else {
                labelFalta.innerText = faltaBs.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' Bs';
                labelFalta.style.color = '#ef4444';
            }
        }

        const pRef = document.getElementById('panel-referencia-principal');
        const metodoActivo = document.querySelector('input[name="metodo-pago"]:checked');
        if (metodoActivo && metodoActivo.value === 'mixto' && pRef) {
            pRef.style.display = (mPmBs > 0) ? 'block' : 'none';
        }
    }

    const inputsMixto = document.querySelectorAll('.input-mixto');
    inputsMixto.forEach(inp => inp.addEventListener('input', calcularPagoMixto));

    radiosPago.forEach(radio => {
        radio.addEventListener('change', (e) => {
            const met = e.target.value;
            const pRef = document.getElementById('panel-referencia-principal');
            const pMix = document.getElementById('panel-pago-mixto');
            const pCred = document.getElementById('panel-credito');
            const pOtro = document.getElementById('panel-otro-detalle');
            
            if(pRef) pRef.style.display = 'none'; 
            if(pMix) pMix.style.display = 'none'; 
            if(pCred) pCred.style.display = 'none';
            if(pOtro) pOtro.style.display = 'none';
            
            if (met === 'pago_movil') { 
                if(pRef) pRef.style.display = 'block'; 
            } 
            else if (met === 'mixto') { 
                if(pMix) pMix.style.display = 'block'; 
                if(pRef) pRef.style.display = 'none'; 
                inputsMixto.forEach(inp => inp.value = ''); 
                if(document.getElementById('mix-otro-detalle')) document.getElementById('mix-otro-detalle').value = '';
                calcularPagoMixto();
            } 
            else if (met === 'credito') { 
                if(pCred) pCred.style.display = 'block'; 
                if(typeof iniciarCamara === 'function') iniciarCamara();
            }
            else if (met === 'otro' || (met && met.startsWith('otro_'))) {
                if(pOtro) pOtro.style.display = 'block'; 
            }
            
            if(met !== 'credito' && typeof detenerCamara === 'function') detenerCamara();
            calcularDeudaCredito();
            calcularVueltoGlobal(); 
        });
    });

    function calcularDeudaCredito() {
        const metodoRadio = document.querySelector('input[name="metodo-pago"]:checked');
        if (!metodoRadio || metodoRadio.value !== 'credito') return;

        const totalCompraUsd = parseFloat((spanTotalUsd?.innerText || '0').replace('$', '')) || 0;
        
        let abUsdFisico = parseFloat(document.getElementById('cred-mix-usd')?.value) || 0;
        let abEfecBs = parseFloat(document.getElementById('cred-mix-efec-bs')?.value) || 0;
        let abPmBs = parseFloat(document.getElementById('cred-mix-pm-bs')?.value) || 0;
        let abPvBs = parseFloat(document.getElementById('cred-mix-pv-bs')?.value) || 0;
        let abBpBs = parseFloat(document.getElementById('cred-mix-bp-bs')?.value) || 0;
        let abOtroUsd = parseFloat(document.getElementById('cred-mix-otro-usd')?.value) || 0;
        
        let abonoTotalUsd = abUsdFisico + abOtroUsd + ((abEfecBs + abPmBs + abPvBs + abBpBs) / tasa);
        let deudaUsd = totalCompraUsd - abonoTotalUsd; 
        if (deudaUsd < 0) deudaUsd = 0; 

        if(spanDeuda) spanDeuda.innerHTML = `$${deudaUsd.toFixed(2)} / ${(deudaUsd * tasa).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Bs`;
        
        const refAbono = document.getElementById('referencia-abono');
        if (refAbono) {
            refAbono.style.display = (abPmBs > 0) ? 'block' : 'none';
        }
    }

    const inputsCreditoMixto = document.querySelectorAll('.input-credito-mixto');
    inputsCreditoMixto.forEach(inp => inp.addEventListener('input', calcularDeudaCredito));

    if (btnFacturar) {
        btnFacturar.addEventListener('click', async () => {
            if (window.carrito.length === 0) { alert('El ticket está vacío. Agrega productos primero.'); return; }
            const radMetodo = document.querySelector('input[name="metodo-pago"]:checked');
            if(!radMetodo) { alert('Selecciona un método de pago.'); return; }
            
            const metodoOriginal = radMetodo.value;
            let metodoSeleccionado = metodoOriginal;
            
            const totalCompraUsd = parseFloat((spanTotalUsd?.innerText || '0').replace('$', '')) || 0;
            const totalCompraBs = Math.round((totalCompraUsd * tasa) * 100) / 100;

            let objMixto = { usd: 0, efecBs: 0, pmBs: 0, pvBs: 0, bpBs: 0, otroUsd: 0 };
            let referenciaFinal = '';
            let mixtoOtroDetalle = '';

            if(metodoOriginal === 'mixto') {
                objMixto.usd = parseFloat(document.getElementById('mix-usd')?.value) || 0;
                objMixto.efecBs = parseFloat(document.getElementById('mix-efec-bs')?.value) || 0;
                objMixto.pmBs = parseFloat(document.getElementById('mix-pm-bs')?.value) || 0;
                objMixto.pvBs = parseFloat(document.getElementById('mix-pv-bs')?.value) || 0;
                objMixto.bpBs = parseFloat(document.getElementById('mix-bp-bs')?.value) || 0;
                objMixto.otroUsd = parseFloat(document.getElementById('mix-otro-usd')?.value) || 0;
                mixtoOtroDetalle = document.getElementById('mix-otro-detalle') ? document.getElementById('mix-otro-detalle').value.trim() : '';
                
                let abonadoTotalBs = (objMixto.usd * tasa) + (objMixto.otroUsd * tasa) + objMixto.efecBs + objMixto.pmBs + objMixto.pvBs + objMixto.bpBs;
                if(abonadoTotalBs < (totalCompraBs - 0.5)) {
                    alert('⚠️ El pago mixto está incompleto. Faltan bolívares por cubrir el total de la factura.');
                    return;
                }
            }

            if (metodoOriginal === 'credito') {
                if (document.getElementById('foto_base64') && document.getElementById('foto_base64').value === '') {
                    alert("🚫 ¡Cámara Obligatoria! Tómale una foto al cliente."); return; 
                }
            }

            let abonoUsd = 0; let deudaUsd = 0; 
            const cedulaCliente = inputCedula ? inputCedula.value.trim() : '';
            const nombreCliente = inputNombre ? inputNombre.value.trim() : '';

            if (metodoOriginal === 'credito') {
                if (cedulaCliente === '' || nombreCliente === '') { alert('⚠️ Para ventas A CRÉDITO es obligatorio registrar Cliente.'); return; }
                
                let abUsdFisico = parseFloat(document.getElementById('cred-mix-usd')?.value) || 0;
                let abEfecBs = parseFloat(document.getElementById('cred-mix-efec-bs')?.value) || 0;
                let abPmBs = parseFloat(document.getElementById('cred-mix-pm-bs')?.value) || 0;
                let abPvBs = parseFloat(document.getElementById('cred-mix-pv-bs')?.value) || 0;
                let abBpBs = parseFloat(document.getElementById('cred-mix-bp-bs')?.value) || 0;
                let abOtroUsd = parseFloat(document.getElementById('cred-mix-otro-usd')?.value) || 0;
                mixtoOtroDetalle = document.getElementById('cred-mix-otro-detalle') ? document.getElementById('cred-mix-otro-detalle').value.trim() : '';
                
                abonoUsd = abUsdFisico + abOtroUsd + ((abEfecBs + abPmBs + abPvBs + abBpBs) / tasa);
                deudaUsd = totalCompraUsd - abonoUsd; if (deudaUsd < 0) deudaUsd = 0;

                const refAbonoElement = document.getElementById('referencia-abono');
                if (abPmBs > 0 && (!refAbonoElement || refAbonoElement.value.trim() === '')) {
                    alert('⚠️ Falta Referencia del Pago Móvil en el Abono.'); return;
                }
                referenciaFinal = refAbonoElement ? refAbonoElement.value.trim() : '';
                
                if (abonoUsd > 0) {
                    metodoSeleccionado = 'credito_mixto';
                    objMixto.usd = abUsdFisico;
                    objMixto.efecBs = abEfecBs;
                    objMixto.pmBs = abPmBs;
                    objMixto.pvBs = abPvBs;
                    objMixto.bpBs = abBpBs;
                    objMixto.otroUsd = abOtroUsd;
                }
            } else {
                abonoUsd = totalCompraUsd; 
                if (metodoOriginal === 'pago_movil') {
                    const refPrinElement = document.getElementById('referencia-principal');
                    if (refPrinElement && refPrinElement.value.trim() === '') {
                        alert('⚠️ El número de referencia bancaria es obligatorio.'); refPrinElement.focus(); return;
                    }
                    referenciaFinal = refPrinElement ? refPrinElement.value.trim() : '';
                }
            }

            btnFacturar.disabled = true; btnFacturar.innerText = 'Procesando...';

            try {
                const obsElement = document.getElementById('observaciones-credito');

                let billeteRecibidoUsd = totalCompraUsd;
                let vueltoDadoUsd = 0;
                let vueltoDadoBs = 0;
                let metodoVueltoBs = '';
                let abonoAClienteUsd = 0; 
                let recibidoBs = totalCompraBs;
                let abonoAClienteBs = 0; 

                if (metodoOriginal === 'efectivo_usd') {
                    const elRecibidoUsd = document.getElementById('recibido-usd') || document.getElementById('monto-recibido-usd');
                    if(elRecibidoUsd && elRecibidoUsd.value) billeteRecibidoUsd = parseFloat(elRecibidoUsd.value.replace(',', '.')) || totalCompraUsd;
                    
                    const elVueltoDadoUsd = document.getElementById('vuelto-dado-usd');
                    if(elVueltoDadoUsd && elVueltoDadoUsd.value) vueltoDadoUsd = parseFloat(elVueltoDadoUsd.value.replace(',', '.')) || 0;
                    
                    const elVueltoDadoBs = document.getElementById('vuelto-dado-bs');
                    if(elVueltoDadoBs && elVueltoDadoBs.value) vueltoDadoBs = parseFloat(elVueltoDadoBs.value.replace(',', '.')) || 0;
                    
                    const elMetVuelto = document.getElementById('metodo-vuelto-bs');
                    if(elMetVuelto) metodoVueltoBs = elMetVuelto.value;
                }

                if (metodoOriginal === 'efectivo_bs') {
                    const elRecibidoBs = document.getElementById('recibido-bs');
                    if(elRecibidoBs && elRecibidoBs.value) recibidoBs = parseFloat(elRecibidoBs.value.replace(',', '.')) || totalCompraBs;
                    
                    const elVueltoUsdDesdeBs = document.getElementById('vuelto-dado-usd-desde-bs');
                    if(elVueltoUsdDesdeBs && elVueltoUsdDesdeBs.value) vueltoDadoUsd = parseFloat(elVueltoUsdDesdeBs.value.replace(',', '.')) || 0;
                    
                    const elVueltoBsDesdeBs = document.getElementById('vuelto-dado-bs-desde-bs');
                    if(elVueltoBsDesdeBs && elVueltoBsDesdeBs.value) vueltoDadoBs = parseFloat(elVueltoBsDesdeBs.value.replace(',', '.')) || 0;
                    
                    const elMetVueltoDesdeBs = document.getElementById('metodo-vuelto-bs-desde-bs');
                    if(elMetVueltoDesdeBs) metodoVueltoBs = elMetVueltoDesdeBs.value;
                }

                const paqueteDatos = {
                    clienteCedula: cedulaCliente, clienteNombre: nombreCliente, tasa: tasa, totalUsd: totalCompraUsd, totalBs: totalCompraBs,
                    metodoPago: metodoSeleccionado, abonoUsd: abonoUsd, deudaUsd: deudaUsd, referencia: referenciaFinal, carrito: window.carrito,
                    foto_base64: document.getElementById('foto_base64') ? document.getElementById('foto_base64').value : '',
                    observaciones: obsElement ? obsElement.value.trim() : '',
                    mixtoUsd: objMixto.usd, mixtoEfecBs: objMixto.efecBs, mixtoPmBs: objMixto.pmBs, mixtoPvBs: objMixto.pvBs, mixtoBpBs: objMixto.bpBs, mixtoOtroUsd: objMixto.otroUsd,
                    mixtoOtroDetalle: mixtoOtroDetalle,
                    billeteRecibidoUsd: billeteRecibidoUsd, vueltoDadoUsd: vueltoDadoUsd, vueltoDadoBs: vueltoDadoBs, metodoVueltoBs: metodoVueltoBs, abonoAClienteUsd: abonoAClienteUsd, recibidoBs: recibidoBs, abonoAClienteBs: abonoAClienteBs
                };

                const respuesta = await fetch('guardar_venta.php', { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/json' }, 
                    body: JSON.stringify(paqueteDatos) 
                });
                
                const resultado = await respuesta.json();
                
                // --- CAMBIO CLAVE AQUI ABAJO ---
                if (resultado.exito) { 
                    window.open('imprimir_ticket.php?id=' + resultado.id_venta, '_blank'); 
                    
                    // En vez de recargar la pagina, llamamos a la funcion que cierra solo esta pestaña
                    if (typeof window.ventaCompletada === 'function') {
                        window.ventaCompletada();
                    } else {
                        window.location.reload(); 
                    }
                    
                    btnFacturar.disabled = false; btnFacturar.innerText = 'Procesar Venta';
                } else { 
                    alert('❌ Error del servidor: ' + resultado.mensaje); 
                    btnFacturar.disabled = false; btnFacturar.innerText = 'Procesar Venta';
                }

            } catch (error) { 
                console.error("Error en JS capturado:", error);
                alert('❌ Error interno. El código chocó antes de enviarse (F12 para ver detalles).'); 
                btnFacturar.disabled = false; 
                btnFacturar.innerText = 'Procesar Venta';
            }
        });
    }
});

window.quitarCliente = function() {
    document.getElementById('cliente-cedula').value = ''; document.getElementById('cliente-nombre').value = '';
    if(document.getElementById('cliente-telefono')) document.getElementById('cliente-telefono').value = '';
    document.getElementById('tarjeta-cliente-seleccionado').style.display = 'none';
    document.getElementById('contenedor-buscador-cliente').style.display = 'block';
};