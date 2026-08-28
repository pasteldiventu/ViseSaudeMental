"""Revision inicial — cria schema completo via metadata."""

from alembic import op
import sqlalchemy as sa

revision = "001_initial"
down_revision = None
branch_labels = None
depends_on = None


def upgrade() -> None:
    from app.database import Base
    from app.models import *  # noqa: F401,F403

    bind = op.get_bind()
    Base.metadata.create_all(bind=bind)


def downgrade() -> None:
    from app.database import Base
    from app.models import *  # noqa: F401,F403

    bind = op.get_bind()
    Base.metadata.drop_all(bind=bind)
