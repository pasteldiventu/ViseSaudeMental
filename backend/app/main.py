from pathlib import Path

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from starlette.middleware.sessions import SessionMiddleware

from app.admin.setup import setup_admin
from app.api.v1.router import api_router
from app.config import settings

STATIC_DIR = Path(__file__).resolve().parent / "static"
STATIC_DIR.mkdir(parents=True, exist_ok=True)


def create_app() -> FastAPI:
    application = FastAPI(
        title=settings.app_name,
        version="1.0.0",
        description="API e CMS do Sistema de Saúde Mental para Adolescentes",
    )

    origins = [
        origin.strip()
        for origin in settings.cors_origins.split(",")
        if origin.strip()
    ]
    application.add_middleware(
        CORSMiddleware,
        allow_origins=origins if origins else ["*"],
        allow_credentials="*" not in origins,
        allow_methods=["*"],
        allow_headers=["*"],
    )
    application.add_middleware(SessionMiddleware, secret_key=settings.secret_key)

    if STATIC_DIR.is_dir():
        application.mount(
            "/static", StaticFiles(directory=STATIC_DIR), name="static"
        )
    application.include_router(api_router, prefix="/api/v1")

    @application.get("/up", tags=["saúde"])
    def health_check():
        return {"status": "ok"}

    setup_admin(application)
    return application


# Lazy: evita crash silencioso no import se o admin falhar em testes parciais.
app = create_app()
