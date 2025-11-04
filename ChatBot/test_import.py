import traceback
try:
    import agente_api
    print('import ok')
except Exception:
    traceback.print_exc()
    raise SystemExit(1)

