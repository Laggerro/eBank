-- Ejecutar una sola vez en el SQL Editor de Supabase.
-- La funcion revierte una venta completa o no aplica ningun cambio.

create extension if not exists pgcrypto;

create or replace function public.anular_pago_posnet(
    p_transaccion_id bigint,
    p_pin text,
    p_codigo_maestro text,
    p_posnet_id bigint,
    p_usuario_posnet text
)
returns table(exito boolean, mensaje text)
language plpgsql
security definer
set search_path = public
as $$
declare
    v_transaccion public.transacciones%rowtype;
    v_alumno public.alumnos%rowtype;
    v_posnet public.usuarios_banco%rowtype;
    v_codigo_maestro constant text := '1';
begin
    if p_codigo_maestro <> v_codigo_maestro then
        return query select false, 'Código maestro inválido.';
        return;
    end if;

    select * into v_transaccion
    from public.transacciones
    where id = p_transaccion_id
      and posnet_id = p_posnet_id
      and tipo = 'COBRO'
      and estado = 'OK'
    for update;

    if not found then
        return query select false, 'La venta no existe, no pertenece a este POSNET o ya fue anulada.';
        return;
    end if;

    select * into v_alumno
    from public.alumnos
    where id = v_transaccion.alumno_id
    for update;

    if not found then
        return query select false, 'No se encontró el alumno de la venta.';
        return;
    end if;

    if not (v_alumno.pin::text = extensions.crypt(p_pin::text, v_alumno.pin::text)
        or v_alumno.pin::text = p_pin::text) then
        return query select false, 'PIN del alumno incorrecto.';
        return;
    end if;

    select * into v_posnet
    from public.usuarios_banco
    where id = p_posnet_id
      and rol = 'POSNET'
      and activo = true
    for update;

    if not found then
        return query select false, 'El POSNET no está habilitado.';
        return;
    end if;

    update public.alumnos
    set saldo = coalesce(v_alumno.saldo, 0) + v_transaccion.monto
    where id = v_alumno.id;

    update public.usuarios_banco
    set monto_acumulado = greatest(0, coalesce(v_posnet.monto_acumulado, 0) - v_transaccion.monto),
        cant_transacciones = greatest(0, coalesce(v_posnet.cant_transacciones, 0) - 1)
    where id = p_posnet_id;

    update public.transacciones
    set estado = 'ANULADA'
    where id = p_transaccion_id;

    insert into public.logs_auditoria(tipo_evento, usuario_origen, usuario_destino, detalle)
    values (
        'ANULACION_COBRO',
        p_usuario_posnet,
        v_alumno.dni::text,
        'Venta #' || p_transaccion_id::text || ' anulada con PIN del alumno y código maestro.'
    );

    return query select true, 'Venta anulada correctamente.';
end;
$$;

grant execute on function public.anular_pago_posnet(bigint, text, text, bigint, text) to anon, authenticated;
