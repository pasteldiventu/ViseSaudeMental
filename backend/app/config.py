from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    app_name: str = "Vise Saúde Mental"
    app_env: str = "local"
    app_debug: bool = True
    app_url: str = "http://localhost:8000"
    secret_key: str = "change-me-in-production-vise-sma-secret"
    access_token_expire_minutes: int = 60 * 24 * 7

    db_host: str = "mysql"
    db_port: int = 3306
    db_name: str = "vise"
    db_user: str = "vise"
    db_password: str = "vise"

    cors_origins: str = "*"

    @property
    def database_url(self) -> str:
        return (
            f"mysql+pymysql://{self.db_user}:{self.db_password}"
            f"@{self.db_host}:{self.db_port}/{self.db_name}?charset=utf8mb4"
        )


@lru_cache
def get_settings() -> Settings:
    return Settings()


settings = get_settings()
