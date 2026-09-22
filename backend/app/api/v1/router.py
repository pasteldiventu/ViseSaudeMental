from fastapi import APIRouter

from app.api.v1 import aplicacoes, auth, avatar, finalizar, respostas, staff, termos


api_router = APIRouter()
api_router.include_router(auth.router)
api_router.include_router(staff.router)
api_router.include_router(aplicacoes.router)
api_router.include_router(respostas.router)
api_router.include_router(finalizar.router)
api_router.include_router(avatar.router)
api_router.include_router(termos.router)

router = api_router
