// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'app_database.dart';

// ignore_for_file: type=lint
class $CachedAplicacaoTable extends CachedAplicacao
    with TableInfo<$CachedAplicacaoTable, CachedAplicacaoData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedAplicacaoTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _tituloMeta = const VerificationMeta('titulo');
  @override
  late final GeneratedColumn<String> titulo = GeneratedColumn<String>(
    'titulo',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _concluidaLocalmenteMeta =
      const VerificationMeta('concluidaLocalmente');
  @override
  late final GeneratedColumn<bool> concluidaLocalmente = GeneratedColumn<bool>(
    'concluida_localmente',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("concluida_localmente" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  static const VerificationMeta _sincronizadaServidorMeta =
      const VerificationMeta('sincronizadaServidor');
  @override
  late final GeneratedColumn<bool> sincronizadaServidor = GeneratedColumn<bool>(
    'sincronizada_servidor',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("sincronizada_servidor" IN (0, 1))',
    ),
    defaultValue: const Constant(false),
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    titulo,
    concluidaLocalmente,
    sincronizadaServidor,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_aplicacao';
  @override
  VerificationContext validateIntegrity(
    Insertable<CachedAplicacaoData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('titulo')) {
      context.handle(
        _tituloMeta,
        titulo.isAcceptableOrUnknown(data['titulo']!, _tituloMeta),
      );
    } else if (isInserting) {
      context.missing(_tituloMeta);
    }
    if (data.containsKey('concluida_localmente')) {
      context.handle(
        _concluidaLocalmenteMeta,
        concluidaLocalmente.isAcceptableOrUnknown(
          data['concluida_localmente']!,
          _concluidaLocalmenteMeta,
        ),
      );
    }
    if (data.containsKey('sincronizada_servidor')) {
      context.handle(
        _sincronizadaServidorMeta,
        sincronizadaServidor.isAcceptableOrUnknown(
          data['sincronizada_servidor']!,
          _sincronizadaServidorMeta,
        ),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  CachedAplicacaoData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedAplicacaoData(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      titulo: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}titulo'],
      )!,
      concluidaLocalmente: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}concluida_localmente'],
      )!,
      sincronizadaServidor: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}sincronizada_servidor'],
      )!,
    );
  }

  @override
  $CachedAplicacaoTable createAlias(String alias) {
    return $CachedAplicacaoTable(attachedDatabase, alias);
  }
}

class CachedAplicacaoData extends DataClass
    implements Insertable<CachedAplicacaoData> {
  final int id;
  final String titulo;
  final bool concluidaLocalmente;
  final bool sincronizadaServidor;
  const CachedAplicacaoData({
    required this.id,
    required this.titulo,
    required this.concluidaLocalmente,
    required this.sincronizadaServidor,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['titulo'] = Variable<String>(titulo);
    map['concluida_localmente'] = Variable<bool>(concluidaLocalmente);
    map['sincronizada_servidor'] = Variable<bool>(sincronizadaServidor);
    return map;
  }

  CachedAplicacaoCompanion toCompanion(bool nullToAbsent) {
    return CachedAplicacaoCompanion(
      id: Value(id),
      titulo: Value(titulo),
      concluidaLocalmente: Value(concluidaLocalmente),
      sincronizadaServidor: Value(sincronizadaServidor),
    );
  }

  factory CachedAplicacaoData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedAplicacaoData(
      id: serializer.fromJson<int>(json['id']),
      titulo: serializer.fromJson<String>(json['titulo']),
      concluidaLocalmente: serializer.fromJson<bool>(
        json['concluidaLocalmente'],
      ),
      sincronizadaServidor: serializer.fromJson<bool>(
        json['sincronizadaServidor'],
      ),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'titulo': serializer.toJson<String>(titulo),
      'concluidaLocalmente': serializer.toJson<bool>(concluidaLocalmente),
      'sincronizadaServidor': serializer.toJson<bool>(sincronizadaServidor),
    };
  }

  CachedAplicacaoData copyWith({
    int? id,
    String? titulo,
    bool? concluidaLocalmente,
    bool? sincronizadaServidor,
  }) => CachedAplicacaoData(
    id: id ?? this.id,
    titulo: titulo ?? this.titulo,
    concluidaLocalmente: concluidaLocalmente ?? this.concluidaLocalmente,
    sincronizadaServidor: sincronizadaServidor ?? this.sincronizadaServidor,
  );
  CachedAplicacaoData copyWithCompanion(CachedAplicacaoCompanion data) {
    return CachedAplicacaoData(
      id: data.id.present ? data.id.value : this.id,
      titulo: data.titulo.present ? data.titulo.value : this.titulo,
      concluidaLocalmente: data.concluidaLocalmente.present
          ? data.concluidaLocalmente.value
          : this.concluidaLocalmente,
      sincronizadaServidor: data.sincronizadaServidor.present
          ? data.sincronizadaServidor.value
          : this.sincronizadaServidor,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedAplicacaoData(')
          ..write('id: $id, ')
          ..write('titulo: $titulo, ')
          ..write('concluidaLocalmente: $concluidaLocalmente, ')
          ..write('sincronizadaServidor: $sincronizadaServidor')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode =>
      Object.hash(id, titulo, concluidaLocalmente, sincronizadaServidor);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedAplicacaoData &&
          other.id == this.id &&
          other.titulo == this.titulo &&
          other.concluidaLocalmente == this.concluidaLocalmente &&
          other.sincronizadaServidor == this.sincronizadaServidor);
}

class CachedAplicacaoCompanion extends UpdateCompanion<CachedAplicacaoData> {
  final Value<int> id;
  final Value<String> titulo;
  final Value<bool> concluidaLocalmente;
  final Value<bool> sincronizadaServidor;
  const CachedAplicacaoCompanion({
    this.id = const Value.absent(),
    this.titulo = const Value.absent(),
    this.concluidaLocalmente = const Value.absent(),
    this.sincronizadaServidor = const Value.absent(),
  });
  CachedAplicacaoCompanion.insert({
    this.id = const Value.absent(),
    required String titulo,
    this.concluidaLocalmente = const Value.absent(),
    this.sincronizadaServidor = const Value.absent(),
  }) : titulo = Value(titulo);
  static Insertable<CachedAplicacaoData> custom({
    Expression<int>? id,
    Expression<String>? titulo,
    Expression<bool>? concluidaLocalmente,
    Expression<bool>? sincronizadaServidor,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (titulo != null) 'titulo': titulo,
      if (concluidaLocalmente != null)
        'concluida_localmente': concluidaLocalmente,
      if (sincronizadaServidor != null)
        'sincronizada_servidor': sincronizadaServidor,
    });
  }

  CachedAplicacaoCompanion copyWith({
    Value<int>? id,
    Value<String>? titulo,
    Value<bool>? concluidaLocalmente,
    Value<bool>? sincronizadaServidor,
  }) {
    return CachedAplicacaoCompanion(
      id: id ?? this.id,
      titulo: titulo ?? this.titulo,
      concluidaLocalmente: concluidaLocalmente ?? this.concluidaLocalmente,
      sincronizadaServidor: sincronizadaServidor ?? this.sincronizadaServidor,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (titulo.present) {
      map['titulo'] = Variable<String>(titulo.value);
    }
    if (concluidaLocalmente.present) {
      map['concluida_localmente'] = Variable<bool>(concluidaLocalmente.value);
    }
    if (sincronizadaServidor.present) {
      map['sincronizada_servidor'] = Variable<bool>(sincronizadaServidor.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedAplicacaoCompanion(')
          ..write('id: $id, ')
          ..write('titulo: $titulo, ')
          ..write('concluidaLocalmente: $concluidaLocalmente, ')
          ..write('sincronizadaServidor: $sincronizadaServidor')
          ..write(')'))
        .toString();
  }
}

class $CachedCategoriaTable extends CachedCategoria
    with TableInfo<$CachedCategoriaTable, CachedCategoriaData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedCategoriaTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _aplicacaoIdMeta = const VerificationMeta(
    'aplicacaoId',
  );
  @override
  late final GeneratedColumn<int> aplicacaoId = GeneratedColumn<int>(
    'aplicacao_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _tituloMeta = const VerificationMeta('titulo');
  @override
  late final GeneratedColumn<String> titulo = GeneratedColumn<String>(
    'titulo',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _mensagemAvatarMeta = const VerificationMeta(
    'mensagemAvatar',
  );
  @override
  late final GeneratedColumn<String> mensagemAvatar = GeneratedColumn<String>(
    'mensagem_avatar',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _imagemUrlMeta = const VerificationMeta(
    'imagemUrl',
  );
  @override
  late final GeneratedColumn<String> imagemUrl = GeneratedColumn<String>(
    'imagem_url',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _imagemLocalMeta = const VerificationMeta(
    'imagemLocal',
  );
  @override
  late final GeneratedColumn<String> imagemLocal = GeneratedColumn<String>(
    'imagem_local',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _ordemMeta = const VerificationMeta('ordem');
  @override
  late final GeneratedColumn<int> ordem = GeneratedColumn<int>(
    'ordem',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    aplicacaoId,
    titulo,
    mensagemAvatar,
    imagemUrl,
    imagemLocal,
    ordem,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_categoria';
  @override
  VerificationContext validateIntegrity(
    Insertable<CachedCategoriaData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('aplicacao_id')) {
      context.handle(
        _aplicacaoIdMeta,
        aplicacaoId.isAcceptableOrUnknown(
          data['aplicacao_id']!,
          _aplicacaoIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_aplicacaoIdMeta);
    }
    if (data.containsKey('titulo')) {
      context.handle(
        _tituloMeta,
        titulo.isAcceptableOrUnknown(data['titulo']!, _tituloMeta),
      );
    } else if (isInserting) {
      context.missing(_tituloMeta);
    }
    if (data.containsKey('mensagem_avatar')) {
      context.handle(
        _mensagemAvatarMeta,
        mensagemAvatar.isAcceptableOrUnknown(
          data['mensagem_avatar']!,
          _mensagemAvatarMeta,
        ),
      );
    }
    if (data.containsKey('imagem_url')) {
      context.handle(
        _imagemUrlMeta,
        imagemUrl.isAcceptableOrUnknown(data['imagem_url']!, _imagemUrlMeta),
      );
    }
    if (data.containsKey('imagem_local')) {
      context.handle(
        _imagemLocalMeta,
        imagemLocal.isAcceptableOrUnknown(
          data['imagem_local']!,
          _imagemLocalMeta,
        ),
      );
    }
    if (data.containsKey('ordem')) {
      context.handle(
        _ordemMeta,
        ordem.isAcceptableOrUnknown(data['ordem']!, _ordemMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {aplicacaoId, id};
  @override
  CachedCategoriaData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedCategoriaData(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      aplicacaoId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}aplicacao_id'],
      )!,
      titulo: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}titulo'],
      )!,
      mensagemAvatar: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}mensagem_avatar'],
      ),
      imagemUrl: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}imagem_url'],
      ),
      imagemLocal: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}imagem_local'],
      ),
      ordem: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}ordem'],
      )!,
    );
  }

  @override
  $CachedCategoriaTable createAlias(String alias) {
    return $CachedCategoriaTable(attachedDatabase, alias);
  }
}

class CachedCategoriaData extends DataClass
    implements Insertable<CachedCategoriaData> {
  final int id;
  final int aplicacaoId;
  final String titulo;
  final String? mensagemAvatar;
  final String? imagemUrl;
  final String? imagemLocal;
  final int ordem;
  const CachedCategoriaData({
    required this.id,
    required this.aplicacaoId,
    required this.titulo,
    this.mensagemAvatar,
    this.imagemUrl,
    this.imagemLocal,
    required this.ordem,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['aplicacao_id'] = Variable<int>(aplicacaoId);
    map['titulo'] = Variable<String>(titulo);
    if (!nullToAbsent || mensagemAvatar != null) {
      map['mensagem_avatar'] = Variable<String>(mensagemAvatar);
    }
    if (!nullToAbsent || imagemUrl != null) {
      map['imagem_url'] = Variable<String>(imagemUrl);
    }
    if (!nullToAbsent || imagemLocal != null) {
      map['imagem_local'] = Variable<String>(imagemLocal);
    }
    map['ordem'] = Variable<int>(ordem);
    return map;
  }

  CachedCategoriaCompanion toCompanion(bool nullToAbsent) {
    return CachedCategoriaCompanion(
      id: Value(id),
      aplicacaoId: Value(aplicacaoId),
      titulo: Value(titulo),
      mensagemAvatar: mensagemAvatar == null && nullToAbsent
          ? const Value.absent()
          : Value(mensagemAvatar),
      imagemUrl: imagemUrl == null && nullToAbsent
          ? const Value.absent()
          : Value(imagemUrl),
      imagemLocal: imagemLocal == null && nullToAbsent
          ? const Value.absent()
          : Value(imagemLocal),
      ordem: Value(ordem),
    );
  }

  factory CachedCategoriaData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedCategoriaData(
      id: serializer.fromJson<int>(json['id']),
      aplicacaoId: serializer.fromJson<int>(json['aplicacaoId']),
      titulo: serializer.fromJson<String>(json['titulo']),
      mensagemAvatar: serializer.fromJson<String?>(json['mensagemAvatar']),
      imagemUrl: serializer.fromJson<String?>(json['imagemUrl']),
      imagemLocal: serializer.fromJson<String?>(json['imagemLocal']),
      ordem: serializer.fromJson<int>(json['ordem']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'aplicacaoId': serializer.toJson<int>(aplicacaoId),
      'titulo': serializer.toJson<String>(titulo),
      'mensagemAvatar': serializer.toJson<String?>(mensagemAvatar),
      'imagemUrl': serializer.toJson<String?>(imagemUrl),
      'imagemLocal': serializer.toJson<String?>(imagemLocal),
      'ordem': serializer.toJson<int>(ordem),
    };
  }

  CachedCategoriaData copyWith({
    int? id,
    int? aplicacaoId,
    String? titulo,
    Value<String?> mensagemAvatar = const Value.absent(),
    Value<String?> imagemUrl = const Value.absent(),
    Value<String?> imagemLocal = const Value.absent(),
    int? ordem,
  }) => CachedCategoriaData(
    id: id ?? this.id,
    aplicacaoId: aplicacaoId ?? this.aplicacaoId,
    titulo: titulo ?? this.titulo,
    mensagemAvatar: mensagemAvatar.present
        ? mensagemAvatar.value
        : this.mensagemAvatar,
    imagemUrl: imagemUrl.present ? imagemUrl.value : this.imagemUrl,
    imagemLocal: imagemLocal.present ? imagemLocal.value : this.imagemLocal,
    ordem: ordem ?? this.ordem,
  );
  CachedCategoriaData copyWithCompanion(CachedCategoriaCompanion data) {
    return CachedCategoriaData(
      id: data.id.present ? data.id.value : this.id,
      aplicacaoId: data.aplicacaoId.present
          ? data.aplicacaoId.value
          : this.aplicacaoId,
      titulo: data.titulo.present ? data.titulo.value : this.titulo,
      mensagemAvatar: data.mensagemAvatar.present
          ? data.mensagemAvatar.value
          : this.mensagemAvatar,
      imagemUrl: data.imagemUrl.present ? data.imagemUrl.value : this.imagemUrl,
      imagemLocal: data.imagemLocal.present
          ? data.imagemLocal.value
          : this.imagemLocal,
      ordem: data.ordem.present ? data.ordem.value : this.ordem,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedCategoriaData(')
          ..write('id: $id, ')
          ..write('aplicacaoId: $aplicacaoId, ')
          ..write('titulo: $titulo, ')
          ..write('mensagemAvatar: $mensagemAvatar, ')
          ..write('imagemUrl: $imagemUrl, ')
          ..write('imagemLocal: $imagemLocal, ')
          ..write('ordem: $ordem')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    aplicacaoId,
    titulo,
    mensagemAvatar,
    imagemUrl,
    imagemLocal,
    ordem,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedCategoriaData &&
          other.id == this.id &&
          other.aplicacaoId == this.aplicacaoId &&
          other.titulo == this.titulo &&
          other.mensagemAvatar == this.mensagemAvatar &&
          other.imagemUrl == this.imagemUrl &&
          other.imagemLocal == this.imagemLocal &&
          other.ordem == this.ordem);
}

class CachedCategoriaCompanion extends UpdateCompanion<CachedCategoriaData> {
  final Value<int> id;
  final Value<int> aplicacaoId;
  final Value<String> titulo;
  final Value<String?> mensagemAvatar;
  final Value<String?> imagemUrl;
  final Value<String?> imagemLocal;
  final Value<int> ordem;
  final Value<int> rowid;
  const CachedCategoriaCompanion({
    this.id = const Value.absent(),
    this.aplicacaoId = const Value.absent(),
    this.titulo = const Value.absent(),
    this.mensagemAvatar = const Value.absent(),
    this.imagemUrl = const Value.absent(),
    this.imagemLocal = const Value.absent(),
    this.ordem = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CachedCategoriaCompanion.insert({
    required int id,
    required int aplicacaoId,
    required String titulo,
    this.mensagemAvatar = const Value.absent(),
    this.imagemUrl = const Value.absent(),
    this.imagemLocal = const Value.absent(),
    this.ordem = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : id = Value(id),
       aplicacaoId = Value(aplicacaoId),
       titulo = Value(titulo);
  static Insertable<CachedCategoriaData> custom({
    Expression<int>? id,
    Expression<int>? aplicacaoId,
    Expression<String>? titulo,
    Expression<String>? mensagemAvatar,
    Expression<String>? imagemUrl,
    Expression<String>? imagemLocal,
    Expression<int>? ordem,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (aplicacaoId != null) 'aplicacao_id': aplicacaoId,
      if (titulo != null) 'titulo': titulo,
      if (mensagemAvatar != null) 'mensagem_avatar': mensagemAvatar,
      if (imagemUrl != null) 'imagem_url': imagemUrl,
      if (imagemLocal != null) 'imagem_local': imagemLocal,
      if (ordem != null) 'ordem': ordem,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CachedCategoriaCompanion copyWith({
    Value<int>? id,
    Value<int>? aplicacaoId,
    Value<String>? titulo,
    Value<String?>? mensagemAvatar,
    Value<String?>? imagemUrl,
    Value<String?>? imagemLocal,
    Value<int>? ordem,
    Value<int>? rowid,
  }) {
    return CachedCategoriaCompanion(
      id: id ?? this.id,
      aplicacaoId: aplicacaoId ?? this.aplicacaoId,
      titulo: titulo ?? this.titulo,
      mensagemAvatar: mensagemAvatar ?? this.mensagemAvatar,
      imagemUrl: imagemUrl ?? this.imagemUrl,
      imagemLocal: imagemLocal ?? this.imagemLocal,
      ordem: ordem ?? this.ordem,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (aplicacaoId.present) {
      map['aplicacao_id'] = Variable<int>(aplicacaoId.value);
    }
    if (titulo.present) {
      map['titulo'] = Variable<String>(titulo.value);
    }
    if (mensagemAvatar.present) {
      map['mensagem_avatar'] = Variable<String>(mensagemAvatar.value);
    }
    if (imagemUrl.present) {
      map['imagem_url'] = Variable<String>(imagemUrl.value);
    }
    if (imagemLocal.present) {
      map['imagem_local'] = Variable<String>(imagemLocal.value);
    }
    if (ordem.present) {
      map['ordem'] = Variable<int>(ordem.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedCategoriaCompanion(')
          ..write('id: $id, ')
          ..write('aplicacaoId: $aplicacaoId, ')
          ..write('titulo: $titulo, ')
          ..write('mensagemAvatar: $mensagemAvatar, ')
          ..write('imagemUrl: $imagemUrl, ')
          ..write('imagemLocal: $imagemLocal, ')
          ..write('ordem: $ordem, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $CachedPerguntaTable extends CachedPergunta
    with TableInfo<$CachedPerguntaTable, CachedPerguntaData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedPerguntaTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _categoriaIdMeta = const VerificationMeta(
    'categoriaId',
  );
  @override
  late final GeneratedColumn<int> categoriaId = GeneratedColumn<int>(
    'categoria_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _textoMeta = const VerificationMeta('texto');
  @override
  late final GeneratedColumn<String> texto = GeneratedColumn<String>(
    'texto',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _tipoMeta = const VerificationMeta('tipo');
  @override
  late final GeneratedColumn<String> tipo = GeneratedColumn<String>(
    'tipo',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('objetiva'),
  );
  static const VerificationMeta _obrigatoriaMeta = const VerificationMeta(
    'obrigatoria',
  );
  @override
  late final GeneratedColumn<bool> obrigatoria = GeneratedColumn<bool>(
    'obrigatoria',
    aliasedName,
    false,
    type: DriftSqlType.bool,
    requiredDuringInsert: false,
    defaultConstraints: GeneratedColumn.constraintIsAlways(
      'CHECK ("obrigatoria" IN (0, 1))',
    ),
    defaultValue: const Constant(true),
  );
  static const VerificationMeta _imagemUrlMeta = const VerificationMeta(
    'imagemUrl',
  );
  @override
  late final GeneratedColumn<String> imagemUrl = GeneratedColumn<String>(
    'imagem_url',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _imagemLocalMeta = const VerificationMeta(
    'imagemLocal',
  );
  @override
  late final GeneratedColumn<String> imagemLocal = GeneratedColumn<String>(
    'imagem_local',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _ordemMeta = const VerificationMeta('ordem');
  @override
  late final GeneratedColumn<int> ordem = GeneratedColumn<int>(
    'ordem',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    categoriaId,
    texto,
    tipo,
    obrigatoria,
    imagemUrl,
    imagemLocal,
    ordem,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_pergunta';
  @override
  VerificationContext validateIntegrity(
    Insertable<CachedPerguntaData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('categoria_id')) {
      context.handle(
        _categoriaIdMeta,
        categoriaId.isAcceptableOrUnknown(
          data['categoria_id']!,
          _categoriaIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_categoriaIdMeta);
    }
    if (data.containsKey('texto')) {
      context.handle(
        _textoMeta,
        texto.isAcceptableOrUnknown(data['texto']!, _textoMeta),
      );
    } else if (isInserting) {
      context.missing(_textoMeta);
    }
    if (data.containsKey('tipo')) {
      context.handle(
        _tipoMeta,
        tipo.isAcceptableOrUnknown(data['tipo']!, _tipoMeta),
      );
    }
    if (data.containsKey('obrigatoria')) {
      context.handle(
        _obrigatoriaMeta,
        obrigatoria.isAcceptableOrUnknown(
          data['obrigatoria']!,
          _obrigatoriaMeta,
        ),
      );
    }
    if (data.containsKey('imagem_url')) {
      context.handle(
        _imagemUrlMeta,
        imagemUrl.isAcceptableOrUnknown(data['imagem_url']!, _imagemUrlMeta),
      );
    }
    if (data.containsKey('imagem_local')) {
      context.handle(
        _imagemLocalMeta,
        imagemLocal.isAcceptableOrUnknown(
          data['imagem_local']!,
          _imagemLocalMeta,
        ),
      );
    }
    if (data.containsKey('ordem')) {
      context.handle(
        _ordemMeta,
        ordem.isAcceptableOrUnknown(data['ordem']!, _ordemMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  CachedPerguntaData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedPerguntaData(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      categoriaId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}categoria_id'],
      )!,
      texto: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}texto'],
      )!,
      tipo: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}tipo'],
      )!,
      obrigatoria: attachedDatabase.typeMapping.read(
        DriftSqlType.bool,
        data['${effectivePrefix}obrigatoria'],
      )!,
      imagemUrl: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}imagem_url'],
      ),
      imagemLocal: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}imagem_local'],
      ),
      ordem: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}ordem'],
      )!,
    );
  }

  @override
  $CachedPerguntaTable createAlias(String alias) {
    return $CachedPerguntaTable(attachedDatabase, alias);
  }
}

class CachedPerguntaData extends DataClass
    implements Insertable<CachedPerguntaData> {
  final int id;
  final int categoriaId;
  final String texto;
  final String tipo;
  final bool obrigatoria;
  final String? imagemUrl;
  final String? imagemLocal;
  final int ordem;
  const CachedPerguntaData({
    required this.id,
    required this.categoriaId,
    required this.texto,
    required this.tipo,
    required this.obrigatoria,
    this.imagemUrl,
    this.imagemLocal,
    required this.ordem,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['categoria_id'] = Variable<int>(categoriaId);
    map['texto'] = Variable<String>(texto);
    map['tipo'] = Variable<String>(tipo);
    map['obrigatoria'] = Variable<bool>(obrigatoria);
    if (!nullToAbsent || imagemUrl != null) {
      map['imagem_url'] = Variable<String>(imagemUrl);
    }
    if (!nullToAbsent || imagemLocal != null) {
      map['imagem_local'] = Variable<String>(imagemLocal);
    }
    map['ordem'] = Variable<int>(ordem);
    return map;
  }

  CachedPerguntaCompanion toCompanion(bool nullToAbsent) {
    return CachedPerguntaCompanion(
      id: Value(id),
      categoriaId: Value(categoriaId),
      texto: Value(texto),
      tipo: Value(tipo),
      obrigatoria: Value(obrigatoria),
      imagemUrl: imagemUrl == null && nullToAbsent
          ? const Value.absent()
          : Value(imagemUrl),
      imagemLocal: imagemLocal == null && nullToAbsent
          ? const Value.absent()
          : Value(imagemLocal),
      ordem: Value(ordem),
    );
  }

  factory CachedPerguntaData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedPerguntaData(
      id: serializer.fromJson<int>(json['id']),
      categoriaId: serializer.fromJson<int>(json['categoriaId']),
      texto: serializer.fromJson<String>(json['texto']),
      tipo: serializer.fromJson<String>(json['tipo']),
      obrigatoria: serializer.fromJson<bool>(json['obrigatoria']),
      imagemUrl: serializer.fromJson<String?>(json['imagemUrl']),
      imagemLocal: serializer.fromJson<String?>(json['imagemLocal']),
      ordem: serializer.fromJson<int>(json['ordem']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'categoriaId': serializer.toJson<int>(categoriaId),
      'texto': serializer.toJson<String>(texto),
      'tipo': serializer.toJson<String>(tipo),
      'obrigatoria': serializer.toJson<bool>(obrigatoria),
      'imagemUrl': serializer.toJson<String?>(imagemUrl),
      'imagemLocal': serializer.toJson<String?>(imagemLocal),
      'ordem': serializer.toJson<int>(ordem),
    };
  }

  CachedPerguntaData copyWith({
    int? id,
    int? categoriaId,
    String? texto,
    String? tipo,
    bool? obrigatoria,
    Value<String?> imagemUrl = const Value.absent(),
    Value<String?> imagemLocal = const Value.absent(),
    int? ordem,
  }) => CachedPerguntaData(
    id: id ?? this.id,
    categoriaId: categoriaId ?? this.categoriaId,
    texto: texto ?? this.texto,
    tipo: tipo ?? this.tipo,
    obrigatoria: obrigatoria ?? this.obrigatoria,
    imagemUrl: imagemUrl.present ? imagemUrl.value : this.imagemUrl,
    imagemLocal: imagemLocal.present ? imagemLocal.value : this.imagemLocal,
    ordem: ordem ?? this.ordem,
  );
  CachedPerguntaData copyWithCompanion(CachedPerguntaCompanion data) {
    return CachedPerguntaData(
      id: data.id.present ? data.id.value : this.id,
      categoriaId: data.categoriaId.present
          ? data.categoriaId.value
          : this.categoriaId,
      texto: data.texto.present ? data.texto.value : this.texto,
      tipo: data.tipo.present ? data.tipo.value : this.tipo,
      obrigatoria: data.obrigatoria.present
          ? data.obrigatoria.value
          : this.obrigatoria,
      imagemUrl: data.imagemUrl.present ? data.imagemUrl.value : this.imagemUrl,
      imagemLocal: data.imagemLocal.present
          ? data.imagemLocal.value
          : this.imagemLocal,
      ordem: data.ordem.present ? data.ordem.value : this.ordem,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedPerguntaData(')
          ..write('id: $id, ')
          ..write('categoriaId: $categoriaId, ')
          ..write('texto: $texto, ')
          ..write('tipo: $tipo, ')
          ..write('obrigatoria: $obrigatoria, ')
          ..write('imagemUrl: $imagemUrl, ')
          ..write('imagemLocal: $imagemLocal, ')
          ..write('ordem: $ordem')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    categoriaId,
    texto,
    tipo,
    obrigatoria,
    imagemUrl,
    imagemLocal,
    ordem,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedPerguntaData &&
          other.id == this.id &&
          other.categoriaId == this.categoriaId &&
          other.texto == this.texto &&
          other.tipo == this.tipo &&
          other.obrigatoria == this.obrigatoria &&
          other.imagemUrl == this.imagemUrl &&
          other.imagemLocal == this.imagemLocal &&
          other.ordem == this.ordem);
}

class CachedPerguntaCompanion extends UpdateCompanion<CachedPerguntaData> {
  final Value<int> id;
  final Value<int> categoriaId;
  final Value<String> texto;
  final Value<String> tipo;
  final Value<bool> obrigatoria;
  final Value<String?> imagemUrl;
  final Value<String?> imagemLocal;
  final Value<int> ordem;
  const CachedPerguntaCompanion({
    this.id = const Value.absent(),
    this.categoriaId = const Value.absent(),
    this.texto = const Value.absent(),
    this.tipo = const Value.absent(),
    this.obrigatoria = const Value.absent(),
    this.imagemUrl = const Value.absent(),
    this.imagemLocal = const Value.absent(),
    this.ordem = const Value.absent(),
  });
  CachedPerguntaCompanion.insert({
    this.id = const Value.absent(),
    required int categoriaId,
    required String texto,
    this.tipo = const Value.absent(),
    this.obrigatoria = const Value.absent(),
    this.imagemUrl = const Value.absent(),
    this.imagemLocal = const Value.absent(),
    this.ordem = const Value.absent(),
  }) : categoriaId = Value(categoriaId),
       texto = Value(texto);
  static Insertable<CachedPerguntaData> custom({
    Expression<int>? id,
    Expression<int>? categoriaId,
    Expression<String>? texto,
    Expression<String>? tipo,
    Expression<bool>? obrigatoria,
    Expression<String>? imagemUrl,
    Expression<String>? imagemLocal,
    Expression<int>? ordem,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (categoriaId != null) 'categoria_id': categoriaId,
      if (texto != null) 'texto': texto,
      if (tipo != null) 'tipo': tipo,
      if (obrigatoria != null) 'obrigatoria': obrigatoria,
      if (imagemUrl != null) 'imagem_url': imagemUrl,
      if (imagemLocal != null) 'imagem_local': imagemLocal,
      if (ordem != null) 'ordem': ordem,
    });
  }

  CachedPerguntaCompanion copyWith({
    Value<int>? id,
    Value<int>? categoriaId,
    Value<String>? texto,
    Value<String>? tipo,
    Value<bool>? obrigatoria,
    Value<String?>? imagemUrl,
    Value<String?>? imagemLocal,
    Value<int>? ordem,
  }) {
    return CachedPerguntaCompanion(
      id: id ?? this.id,
      categoriaId: categoriaId ?? this.categoriaId,
      texto: texto ?? this.texto,
      tipo: tipo ?? this.tipo,
      obrigatoria: obrigatoria ?? this.obrigatoria,
      imagemUrl: imagemUrl ?? this.imagemUrl,
      imagemLocal: imagemLocal ?? this.imagemLocal,
      ordem: ordem ?? this.ordem,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (categoriaId.present) {
      map['categoria_id'] = Variable<int>(categoriaId.value);
    }
    if (texto.present) {
      map['texto'] = Variable<String>(texto.value);
    }
    if (tipo.present) {
      map['tipo'] = Variable<String>(tipo.value);
    }
    if (obrigatoria.present) {
      map['obrigatoria'] = Variable<bool>(obrigatoria.value);
    }
    if (imagemUrl.present) {
      map['imagem_url'] = Variable<String>(imagemUrl.value);
    }
    if (imagemLocal.present) {
      map['imagem_local'] = Variable<String>(imagemLocal.value);
    }
    if (ordem.present) {
      map['ordem'] = Variable<int>(ordem.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedPerguntaCompanion(')
          ..write('id: $id, ')
          ..write('categoriaId: $categoriaId, ')
          ..write('texto: $texto, ')
          ..write('tipo: $tipo, ')
          ..write('obrigatoria: $obrigatoria, ')
          ..write('imagemUrl: $imagemUrl, ')
          ..write('imagemLocal: $imagemLocal, ')
          ..write('ordem: $ordem')
          ..write(')'))
        .toString();
  }
}

class $CachedOpcaoTable extends CachedOpcao
    with TableInfo<$CachedOpcaoTable, CachedOpcaoData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedOpcaoTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _perguntaIdMeta = const VerificationMeta(
    'perguntaId',
  );
  @override
  late final GeneratedColumn<int> perguntaId = GeneratedColumn<int>(
    'pergunta_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _descricaoMeta = const VerificationMeta(
    'descricao',
  );
  @override
  late final GeneratedColumn<String> descricao = GeneratedColumn<String>(
    'descricao',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _emojiMeta = const VerificationMeta('emoji');
  @override
  late final GeneratedColumn<String> emoji = GeneratedColumn<String>(
    'emoji',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _ordemMeta = const VerificationMeta('ordem');
  @override
  late final GeneratedColumn<int> ordem = GeneratedColumn<int>(
    'ordem',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    perguntaId,
    descricao,
    emoji,
    ordem,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_opcao';
  @override
  VerificationContext validateIntegrity(
    Insertable<CachedOpcaoData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('pergunta_id')) {
      context.handle(
        _perguntaIdMeta,
        perguntaId.isAcceptableOrUnknown(data['pergunta_id']!, _perguntaIdMeta),
      );
    } else if (isInserting) {
      context.missing(_perguntaIdMeta);
    }
    if (data.containsKey('descricao')) {
      context.handle(
        _descricaoMeta,
        descricao.isAcceptableOrUnknown(data['descricao']!, _descricaoMeta),
      );
    } else if (isInserting) {
      context.missing(_descricaoMeta);
    }
    if (data.containsKey('emoji')) {
      context.handle(
        _emojiMeta,
        emoji.isAcceptableOrUnknown(data['emoji']!, _emojiMeta),
      );
    }
    if (data.containsKey('ordem')) {
      context.handle(
        _ordemMeta,
        ordem.isAcceptableOrUnknown(data['ordem']!, _ordemMeta),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  CachedOpcaoData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedOpcaoData(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      perguntaId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}pergunta_id'],
      )!,
      descricao: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}descricao'],
      )!,
      emoji: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}emoji'],
      ),
      ordem: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}ordem'],
      )!,
    );
  }

  @override
  $CachedOpcaoTable createAlias(String alias) {
    return $CachedOpcaoTable(attachedDatabase, alias);
  }
}

class CachedOpcaoData extends DataClass implements Insertable<CachedOpcaoData> {
  final int id;
  final int perguntaId;
  final String descricao;
  final String? emoji;
  final int ordem;
  const CachedOpcaoData({
    required this.id,
    required this.perguntaId,
    required this.descricao,
    this.emoji,
    required this.ordem,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['pergunta_id'] = Variable<int>(perguntaId);
    map['descricao'] = Variable<String>(descricao);
    if (!nullToAbsent || emoji != null) {
      map['emoji'] = Variable<String>(emoji);
    }
    map['ordem'] = Variable<int>(ordem);
    return map;
  }

  CachedOpcaoCompanion toCompanion(bool nullToAbsent) {
    return CachedOpcaoCompanion(
      id: Value(id),
      perguntaId: Value(perguntaId),
      descricao: Value(descricao),
      emoji: emoji == null && nullToAbsent
          ? const Value.absent()
          : Value(emoji),
      ordem: Value(ordem),
    );
  }

  factory CachedOpcaoData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedOpcaoData(
      id: serializer.fromJson<int>(json['id']),
      perguntaId: serializer.fromJson<int>(json['perguntaId']),
      descricao: serializer.fromJson<String>(json['descricao']),
      emoji: serializer.fromJson<String?>(json['emoji']),
      ordem: serializer.fromJson<int>(json['ordem']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'perguntaId': serializer.toJson<int>(perguntaId),
      'descricao': serializer.toJson<String>(descricao),
      'emoji': serializer.toJson<String?>(emoji),
      'ordem': serializer.toJson<int>(ordem),
    };
  }

  CachedOpcaoData copyWith({
    int? id,
    int? perguntaId,
    String? descricao,
    Value<String?> emoji = const Value.absent(),
    int? ordem,
  }) => CachedOpcaoData(
    id: id ?? this.id,
    perguntaId: perguntaId ?? this.perguntaId,
    descricao: descricao ?? this.descricao,
    emoji: emoji.present ? emoji.value : this.emoji,
    ordem: ordem ?? this.ordem,
  );
  CachedOpcaoData copyWithCompanion(CachedOpcaoCompanion data) {
    return CachedOpcaoData(
      id: data.id.present ? data.id.value : this.id,
      perguntaId: data.perguntaId.present
          ? data.perguntaId.value
          : this.perguntaId,
      descricao: data.descricao.present ? data.descricao.value : this.descricao,
      emoji: data.emoji.present ? data.emoji.value : this.emoji,
      ordem: data.ordem.present ? data.ordem.value : this.ordem,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedOpcaoData(')
          ..write('id: $id, ')
          ..write('perguntaId: $perguntaId, ')
          ..write('descricao: $descricao, ')
          ..write('emoji: $emoji, ')
          ..write('ordem: $ordem')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, perguntaId, descricao, emoji, ordem);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedOpcaoData &&
          other.id == this.id &&
          other.perguntaId == this.perguntaId &&
          other.descricao == this.descricao &&
          other.emoji == this.emoji &&
          other.ordem == this.ordem);
}

class CachedOpcaoCompanion extends UpdateCompanion<CachedOpcaoData> {
  final Value<int> id;
  final Value<int> perguntaId;
  final Value<String> descricao;
  final Value<String?> emoji;
  final Value<int> ordem;
  const CachedOpcaoCompanion({
    this.id = const Value.absent(),
    this.perguntaId = const Value.absent(),
    this.descricao = const Value.absent(),
    this.emoji = const Value.absent(),
    this.ordem = const Value.absent(),
  });
  CachedOpcaoCompanion.insert({
    this.id = const Value.absent(),
    required int perguntaId,
    required String descricao,
    this.emoji = const Value.absent(),
    this.ordem = const Value.absent(),
  }) : perguntaId = Value(perguntaId),
       descricao = Value(descricao);
  static Insertable<CachedOpcaoData> custom({
    Expression<int>? id,
    Expression<int>? perguntaId,
    Expression<String>? descricao,
    Expression<String>? emoji,
    Expression<int>? ordem,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (perguntaId != null) 'pergunta_id': perguntaId,
      if (descricao != null) 'descricao': descricao,
      if (emoji != null) 'emoji': emoji,
      if (ordem != null) 'ordem': ordem,
    });
  }

  CachedOpcaoCompanion copyWith({
    Value<int>? id,
    Value<int>? perguntaId,
    Value<String>? descricao,
    Value<String?>? emoji,
    Value<int>? ordem,
  }) {
    return CachedOpcaoCompanion(
      id: id ?? this.id,
      perguntaId: perguntaId ?? this.perguntaId,
      descricao: descricao ?? this.descricao,
      emoji: emoji ?? this.emoji,
      ordem: ordem ?? this.ordem,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (perguntaId.present) {
      map['pergunta_id'] = Variable<int>(perguntaId.value);
    }
    if (descricao.present) {
      map['descricao'] = Variable<String>(descricao.value);
    }
    if (emoji.present) {
      map['emoji'] = Variable<String>(emoji.value);
    }
    if (ordem.present) {
      map['ordem'] = Variable<int>(ordem.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedOpcaoCompanion(')
          ..write('id: $id, ')
          ..write('perguntaId: $perguntaId, ')
          ..write('descricao: $descricao, ')
          ..write('emoji: $emoji, ')
          ..write('ordem: $ordem')
          ..write(')'))
        .toString();
  }
}

class $OutboxRespostaTable extends OutboxResposta
    with TableInfo<$OutboxRespostaTable, OutboxRespostaData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $OutboxRespostaTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _clientUuidMeta = const VerificationMeta(
    'clientUuid',
  );
  @override
  late final GeneratedColumn<String> clientUuid = GeneratedColumn<String>(
    'client_uuid',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _aplicacaoIdMeta = const VerificationMeta(
    'aplicacaoId',
  );
  @override
  late final GeneratedColumn<int> aplicacaoId = GeneratedColumn<int>(
    'aplicacao_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _perguntaIdMeta = const VerificationMeta(
    'perguntaId',
  );
  @override
  late final GeneratedColumn<int> perguntaId = GeneratedColumn<int>(
    'pergunta_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pendente'),
  );
  static const VerificationMeta _payloadMeta = const VerificationMeta(
    'payload',
  );
  @override
  late final GeneratedColumn<String> payload = GeneratedColumn<String>(
    'payload',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _criadoEmMeta = const VerificationMeta(
    'criadoEm',
  );
  @override
  late final GeneratedColumn<DateTime> criadoEm = GeneratedColumn<DateTime>(
    'criado_em',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    clientUuid,
    aplicacaoId,
    perguntaId,
    status,
    payload,
    criadoEm,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'outbox_resposta';
  @override
  VerificationContext validateIntegrity(
    Insertable<OutboxRespostaData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('client_uuid')) {
      context.handle(
        _clientUuidMeta,
        clientUuid.isAcceptableOrUnknown(data['client_uuid']!, _clientUuidMeta),
      );
    } else if (isInserting) {
      context.missing(_clientUuidMeta);
    }
    if (data.containsKey('aplicacao_id')) {
      context.handle(
        _aplicacaoIdMeta,
        aplicacaoId.isAcceptableOrUnknown(
          data['aplicacao_id']!,
          _aplicacaoIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_aplicacaoIdMeta);
    }
    if (data.containsKey('pergunta_id')) {
      context.handle(
        _perguntaIdMeta,
        perguntaId.isAcceptableOrUnknown(data['pergunta_id']!, _perguntaIdMeta),
      );
    } else if (isInserting) {
      context.missing(_perguntaIdMeta);
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    }
    if (data.containsKey('payload')) {
      context.handle(
        _payloadMeta,
        payload.isAcceptableOrUnknown(data['payload']!, _payloadMeta),
      );
    } else if (isInserting) {
      context.missing(_payloadMeta);
    }
    if (data.containsKey('criado_em')) {
      context.handle(
        _criadoEmMeta,
        criadoEm.isAcceptableOrUnknown(data['criado_em']!, _criadoEmMeta),
      );
    } else if (isInserting) {
      context.missing(_criadoEmMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {clientUuid};
  @override
  OutboxRespostaData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return OutboxRespostaData(
      clientUuid: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}client_uuid'],
      )!,
      aplicacaoId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}aplicacao_id'],
      )!,
      perguntaId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}pergunta_id'],
      )!,
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      payload: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}payload'],
      )!,
      criadoEm: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}criado_em'],
      )!,
    );
  }

  @override
  $OutboxRespostaTable createAlias(String alias) {
    return $OutboxRespostaTable(attachedDatabase, alias);
  }
}

class OutboxRespostaData extends DataClass
    implements Insertable<OutboxRespostaData> {
  final String clientUuid;
  final int aplicacaoId;
  final int perguntaId;
  final String status;
  final String payload;
  final DateTime criadoEm;
  const OutboxRespostaData({
    required this.clientUuid,
    required this.aplicacaoId,
    required this.perguntaId,
    required this.status,
    required this.payload,
    required this.criadoEm,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['client_uuid'] = Variable<String>(clientUuid);
    map['aplicacao_id'] = Variable<int>(aplicacaoId);
    map['pergunta_id'] = Variable<int>(perguntaId);
    map['status'] = Variable<String>(status);
    map['payload'] = Variable<String>(payload);
    map['criado_em'] = Variable<DateTime>(criadoEm);
    return map;
  }

  OutboxRespostaCompanion toCompanion(bool nullToAbsent) {
    return OutboxRespostaCompanion(
      clientUuid: Value(clientUuid),
      aplicacaoId: Value(aplicacaoId),
      perguntaId: Value(perguntaId),
      status: Value(status),
      payload: Value(payload),
      criadoEm: Value(criadoEm),
    );
  }

  factory OutboxRespostaData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return OutboxRespostaData(
      clientUuid: serializer.fromJson<String>(json['clientUuid']),
      aplicacaoId: serializer.fromJson<int>(json['aplicacaoId']),
      perguntaId: serializer.fromJson<int>(json['perguntaId']),
      status: serializer.fromJson<String>(json['status']),
      payload: serializer.fromJson<String>(json['payload']),
      criadoEm: serializer.fromJson<DateTime>(json['criadoEm']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'clientUuid': serializer.toJson<String>(clientUuid),
      'aplicacaoId': serializer.toJson<int>(aplicacaoId),
      'perguntaId': serializer.toJson<int>(perguntaId),
      'status': serializer.toJson<String>(status),
      'payload': serializer.toJson<String>(payload),
      'criadoEm': serializer.toJson<DateTime>(criadoEm),
    };
  }

  OutboxRespostaData copyWith({
    String? clientUuid,
    int? aplicacaoId,
    int? perguntaId,
    String? status,
    String? payload,
    DateTime? criadoEm,
  }) => OutboxRespostaData(
    clientUuid: clientUuid ?? this.clientUuid,
    aplicacaoId: aplicacaoId ?? this.aplicacaoId,
    perguntaId: perguntaId ?? this.perguntaId,
    status: status ?? this.status,
    payload: payload ?? this.payload,
    criadoEm: criadoEm ?? this.criadoEm,
  );
  OutboxRespostaData copyWithCompanion(OutboxRespostaCompanion data) {
    return OutboxRespostaData(
      clientUuid: data.clientUuid.present
          ? data.clientUuid.value
          : this.clientUuid,
      aplicacaoId: data.aplicacaoId.present
          ? data.aplicacaoId.value
          : this.aplicacaoId,
      perguntaId: data.perguntaId.present
          ? data.perguntaId.value
          : this.perguntaId,
      status: data.status.present ? data.status.value : this.status,
      payload: data.payload.present ? data.payload.value : this.payload,
      criadoEm: data.criadoEm.present ? data.criadoEm.value : this.criadoEm,
    );
  }

  @override
  String toString() {
    return (StringBuffer('OutboxRespostaData(')
          ..write('clientUuid: $clientUuid, ')
          ..write('aplicacaoId: $aplicacaoId, ')
          ..write('perguntaId: $perguntaId, ')
          ..write('status: $status, ')
          ..write('payload: $payload, ')
          ..write('criadoEm: $criadoEm')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    clientUuid,
    aplicacaoId,
    perguntaId,
    status,
    payload,
    criadoEm,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is OutboxRespostaData &&
          other.clientUuid == this.clientUuid &&
          other.aplicacaoId == this.aplicacaoId &&
          other.perguntaId == this.perguntaId &&
          other.status == this.status &&
          other.payload == this.payload &&
          other.criadoEm == this.criadoEm);
}

class OutboxRespostaCompanion extends UpdateCompanion<OutboxRespostaData> {
  final Value<String> clientUuid;
  final Value<int> aplicacaoId;
  final Value<int> perguntaId;
  final Value<String> status;
  final Value<String> payload;
  final Value<DateTime> criadoEm;
  final Value<int> rowid;
  const OutboxRespostaCompanion({
    this.clientUuid = const Value.absent(),
    this.aplicacaoId = const Value.absent(),
    this.perguntaId = const Value.absent(),
    this.status = const Value.absent(),
    this.payload = const Value.absent(),
    this.criadoEm = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  OutboxRespostaCompanion.insert({
    required String clientUuid,
    required int aplicacaoId,
    required int perguntaId,
    this.status = const Value.absent(),
    required String payload,
    required DateTime criadoEm,
    this.rowid = const Value.absent(),
  }) : clientUuid = Value(clientUuid),
       aplicacaoId = Value(aplicacaoId),
       perguntaId = Value(perguntaId),
       payload = Value(payload),
       criadoEm = Value(criadoEm);
  static Insertable<OutboxRespostaData> custom({
    Expression<String>? clientUuid,
    Expression<int>? aplicacaoId,
    Expression<int>? perguntaId,
    Expression<String>? status,
    Expression<String>? payload,
    Expression<DateTime>? criadoEm,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (clientUuid != null) 'client_uuid': clientUuid,
      if (aplicacaoId != null) 'aplicacao_id': aplicacaoId,
      if (perguntaId != null) 'pergunta_id': perguntaId,
      if (status != null) 'status': status,
      if (payload != null) 'payload': payload,
      if (criadoEm != null) 'criado_em': criadoEm,
      if (rowid != null) 'rowid': rowid,
    });
  }

  OutboxRespostaCompanion copyWith({
    Value<String>? clientUuid,
    Value<int>? aplicacaoId,
    Value<int>? perguntaId,
    Value<String>? status,
    Value<String>? payload,
    Value<DateTime>? criadoEm,
    Value<int>? rowid,
  }) {
    return OutboxRespostaCompanion(
      clientUuid: clientUuid ?? this.clientUuid,
      aplicacaoId: aplicacaoId ?? this.aplicacaoId,
      perguntaId: perguntaId ?? this.perguntaId,
      status: status ?? this.status,
      payload: payload ?? this.payload,
      criadoEm: criadoEm ?? this.criadoEm,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (clientUuid.present) {
      map['client_uuid'] = Variable<String>(clientUuid.value);
    }
    if (aplicacaoId.present) {
      map['aplicacao_id'] = Variable<int>(aplicacaoId.value);
    }
    if (perguntaId.present) {
      map['pergunta_id'] = Variable<int>(perguntaId.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (payload.present) {
      map['payload'] = Variable<String>(payload.value);
    }
    if (criadoEm.present) {
      map['criado_em'] = Variable<DateTime>(criadoEm.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('OutboxRespostaCompanion(')
          ..write('clientUuid: $clientUuid, ')
          ..write('aplicacaoId: $aplicacaoId, ')
          ..write('perguntaId: $perguntaId, ')
          ..write('status: $status, ')
          ..write('payload: $payload, ')
          ..write('criadoEm: $criadoEm, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $LocalProgressTable extends LocalProgress
    with TableInfo<$LocalProgressTable, LocalProgressData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $LocalProgressTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _aplicacaoIdMeta = const VerificationMeta(
    'aplicacaoId',
  );
  @override
  late final GeneratedColumn<int> aplicacaoId = GeneratedColumn<int>(
    'aplicacao_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _perguntaIdMeta = const VerificationMeta(
    'perguntaId',
  );
  @override
  late final GeneratedColumn<int> perguntaId = GeneratedColumn<int>(
    'pergunta_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _categoriaIdMeta = const VerificationMeta(
    'categoriaId',
  );
  @override
  late final GeneratedColumn<int> categoriaId = GeneratedColumn<int>(
    'categoria_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _respostaJsonMeta = const VerificationMeta(
    'respostaJson',
  );
  @override
  late final GeneratedColumn<String> respostaJson = GeneratedColumn<String>(
    'resposta_json',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _respondidoEmMeta = const VerificationMeta(
    'respondidoEm',
  );
  @override
  late final GeneratedColumn<DateTime> respondidoEm = GeneratedColumn<DateTime>(
    'respondido_em',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    aplicacaoId,
    perguntaId,
    categoriaId,
    respostaJson,
    respondidoEm,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'local_progress';
  @override
  VerificationContext validateIntegrity(
    Insertable<LocalProgressData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('aplicacao_id')) {
      context.handle(
        _aplicacaoIdMeta,
        aplicacaoId.isAcceptableOrUnknown(
          data['aplicacao_id']!,
          _aplicacaoIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_aplicacaoIdMeta);
    }
    if (data.containsKey('pergunta_id')) {
      context.handle(
        _perguntaIdMeta,
        perguntaId.isAcceptableOrUnknown(data['pergunta_id']!, _perguntaIdMeta),
      );
    } else if (isInserting) {
      context.missing(_perguntaIdMeta);
    }
    if (data.containsKey('categoria_id')) {
      context.handle(
        _categoriaIdMeta,
        categoriaId.isAcceptableOrUnknown(
          data['categoria_id']!,
          _categoriaIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_categoriaIdMeta);
    }
    if (data.containsKey('resposta_json')) {
      context.handle(
        _respostaJsonMeta,
        respostaJson.isAcceptableOrUnknown(
          data['resposta_json']!,
          _respostaJsonMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_respostaJsonMeta);
    }
    if (data.containsKey('respondido_em')) {
      context.handle(
        _respondidoEmMeta,
        respondidoEm.isAcceptableOrUnknown(
          data['respondido_em']!,
          _respondidoEmMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_respondidoEmMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {aplicacaoId, perguntaId};
  @override
  LocalProgressData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LocalProgressData(
      aplicacaoId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}aplicacao_id'],
      )!,
      perguntaId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}pergunta_id'],
      )!,
      categoriaId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}categoria_id'],
      )!,
      respostaJson: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}resposta_json'],
      )!,
      respondidoEm: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}respondido_em'],
      )!,
    );
  }

  @override
  $LocalProgressTable createAlias(String alias) {
    return $LocalProgressTable(attachedDatabase, alias);
  }
}

class LocalProgressData extends DataClass
    implements Insertable<LocalProgressData> {
  final int aplicacaoId;
  final int perguntaId;
  final int categoriaId;
  final String respostaJson;
  final DateTime respondidoEm;
  const LocalProgressData({
    required this.aplicacaoId,
    required this.perguntaId,
    required this.categoriaId,
    required this.respostaJson,
    required this.respondidoEm,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['aplicacao_id'] = Variable<int>(aplicacaoId);
    map['pergunta_id'] = Variable<int>(perguntaId);
    map['categoria_id'] = Variable<int>(categoriaId);
    map['resposta_json'] = Variable<String>(respostaJson);
    map['respondido_em'] = Variable<DateTime>(respondidoEm);
    return map;
  }

  LocalProgressCompanion toCompanion(bool nullToAbsent) {
    return LocalProgressCompanion(
      aplicacaoId: Value(aplicacaoId),
      perguntaId: Value(perguntaId),
      categoriaId: Value(categoriaId),
      respostaJson: Value(respostaJson),
      respondidoEm: Value(respondidoEm),
    );
  }

  factory LocalProgressData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LocalProgressData(
      aplicacaoId: serializer.fromJson<int>(json['aplicacaoId']),
      perguntaId: serializer.fromJson<int>(json['perguntaId']),
      categoriaId: serializer.fromJson<int>(json['categoriaId']),
      respostaJson: serializer.fromJson<String>(json['respostaJson']),
      respondidoEm: serializer.fromJson<DateTime>(json['respondidoEm']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'aplicacaoId': serializer.toJson<int>(aplicacaoId),
      'perguntaId': serializer.toJson<int>(perguntaId),
      'categoriaId': serializer.toJson<int>(categoriaId),
      'respostaJson': serializer.toJson<String>(respostaJson),
      'respondidoEm': serializer.toJson<DateTime>(respondidoEm),
    };
  }

  LocalProgressData copyWith({
    int? aplicacaoId,
    int? perguntaId,
    int? categoriaId,
    String? respostaJson,
    DateTime? respondidoEm,
  }) => LocalProgressData(
    aplicacaoId: aplicacaoId ?? this.aplicacaoId,
    perguntaId: perguntaId ?? this.perguntaId,
    categoriaId: categoriaId ?? this.categoriaId,
    respostaJson: respostaJson ?? this.respostaJson,
    respondidoEm: respondidoEm ?? this.respondidoEm,
  );
  LocalProgressData copyWithCompanion(LocalProgressCompanion data) {
    return LocalProgressData(
      aplicacaoId: data.aplicacaoId.present
          ? data.aplicacaoId.value
          : this.aplicacaoId,
      perguntaId: data.perguntaId.present
          ? data.perguntaId.value
          : this.perguntaId,
      categoriaId: data.categoriaId.present
          ? data.categoriaId.value
          : this.categoriaId,
      respostaJson: data.respostaJson.present
          ? data.respostaJson.value
          : this.respostaJson,
      respondidoEm: data.respondidoEm.present
          ? data.respondidoEm.value
          : this.respondidoEm,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LocalProgressData(')
          ..write('aplicacaoId: $aplicacaoId, ')
          ..write('perguntaId: $perguntaId, ')
          ..write('categoriaId: $categoriaId, ')
          ..write('respostaJson: $respostaJson, ')
          ..write('respondidoEm: $respondidoEm')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    aplicacaoId,
    perguntaId,
    categoriaId,
    respostaJson,
    respondidoEm,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LocalProgressData &&
          other.aplicacaoId == this.aplicacaoId &&
          other.perguntaId == this.perguntaId &&
          other.categoriaId == this.categoriaId &&
          other.respostaJson == this.respostaJson &&
          other.respondidoEm == this.respondidoEm);
}

class LocalProgressCompanion extends UpdateCompanion<LocalProgressData> {
  final Value<int> aplicacaoId;
  final Value<int> perguntaId;
  final Value<int> categoriaId;
  final Value<String> respostaJson;
  final Value<DateTime> respondidoEm;
  final Value<int> rowid;
  const LocalProgressCompanion({
    this.aplicacaoId = const Value.absent(),
    this.perguntaId = const Value.absent(),
    this.categoriaId = const Value.absent(),
    this.respostaJson = const Value.absent(),
    this.respondidoEm = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  LocalProgressCompanion.insert({
    required int aplicacaoId,
    required int perguntaId,
    required int categoriaId,
    required String respostaJson,
    required DateTime respondidoEm,
    this.rowid = const Value.absent(),
  }) : aplicacaoId = Value(aplicacaoId),
       perguntaId = Value(perguntaId),
       categoriaId = Value(categoriaId),
       respostaJson = Value(respostaJson),
       respondidoEm = Value(respondidoEm);
  static Insertable<LocalProgressData> custom({
    Expression<int>? aplicacaoId,
    Expression<int>? perguntaId,
    Expression<int>? categoriaId,
    Expression<String>? respostaJson,
    Expression<DateTime>? respondidoEm,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (aplicacaoId != null) 'aplicacao_id': aplicacaoId,
      if (perguntaId != null) 'pergunta_id': perguntaId,
      if (categoriaId != null) 'categoria_id': categoriaId,
      if (respostaJson != null) 'resposta_json': respostaJson,
      if (respondidoEm != null) 'respondido_em': respondidoEm,
      if (rowid != null) 'rowid': rowid,
    });
  }

  LocalProgressCompanion copyWith({
    Value<int>? aplicacaoId,
    Value<int>? perguntaId,
    Value<int>? categoriaId,
    Value<String>? respostaJson,
    Value<DateTime>? respondidoEm,
    Value<int>? rowid,
  }) {
    return LocalProgressCompanion(
      aplicacaoId: aplicacaoId ?? this.aplicacaoId,
      perguntaId: perguntaId ?? this.perguntaId,
      categoriaId: categoriaId ?? this.categoriaId,
      respostaJson: respostaJson ?? this.respostaJson,
      respondidoEm: respondidoEm ?? this.respondidoEm,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (aplicacaoId.present) {
      map['aplicacao_id'] = Variable<int>(aplicacaoId.value);
    }
    if (perguntaId.present) {
      map['pergunta_id'] = Variable<int>(perguntaId.value);
    }
    if (categoriaId.present) {
      map['categoria_id'] = Variable<int>(categoriaId.value);
    }
    if (respostaJson.present) {
      map['resposta_json'] = Variable<String>(respostaJson.value);
    }
    if (respondidoEm.present) {
      map['respondido_em'] = Variable<DateTime>(respondidoEm.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalProgressCompanion(')
          ..write('aplicacaoId: $aplicacaoId, ')
          ..write('perguntaId: $perguntaId, ')
          ..write('categoriaId: $categoriaId, ')
          ..write('respostaJson: $respostaJson, ')
          ..write('respondidoEm: $respondidoEm, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

abstract class _$AppDatabase extends GeneratedDatabase {
  _$AppDatabase(QueryExecutor e) : super(e);
  $AppDatabaseManager get managers => $AppDatabaseManager(this);
  late final $CachedAplicacaoTable cachedAplicacao = $CachedAplicacaoTable(
    this,
  );
  late final $CachedCategoriaTable cachedCategoria = $CachedCategoriaTable(
    this,
  );
  late final $CachedPerguntaTable cachedPergunta = $CachedPerguntaTable(this);
  late final $CachedOpcaoTable cachedOpcao = $CachedOpcaoTable(this);
  late final $OutboxRespostaTable outboxResposta = $OutboxRespostaTable(this);
  late final $LocalProgressTable localProgress = $LocalProgressTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
    cachedAplicacao,
    cachedCategoria,
    cachedPergunta,
    cachedOpcao,
    outboxResposta,
    localProgress,
  ];
}

typedef $$CachedAplicacaoTableCreateCompanionBuilder =
    CachedAplicacaoCompanion Function({
      Value<int> id,
      required String titulo,
      Value<bool> concluidaLocalmente,
      Value<bool> sincronizadaServidor,
    });
typedef $$CachedAplicacaoTableUpdateCompanionBuilder =
    CachedAplicacaoCompanion Function({
      Value<int> id,
      Value<String> titulo,
      Value<bool> concluidaLocalmente,
      Value<bool> sincronizadaServidor,
    });

class $$CachedAplicacaoTableFilterComposer
    extends Composer<_$AppDatabase, $CachedAplicacaoTable> {
  $$CachedAplicacaoTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get titulo => $composableBuilder(
    column: $table.titulo,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get concluidaLocalmente => $composableBuilder(
    column: $table.concluidaLocalmente,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get sincronizadaServidor => $composableBuilder(
    column: $table.sincronizadaServidor,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CachedAplicacaoTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedAplicacaoTable> {
  $$CachedAplicacaoTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get titulo => $composableBuilder(
    column: $table.titulo,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get concluidaLocalmente => $composableBuilder(
    column: $table.concluidaLocalmente,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get sincronizadaServidor => $composableBuilder(
    column: $table.sincronizadaServidor,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CachedAplicacaoTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedAplicacaoTable> {
  $$CachedAplicacaoTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get titulo =>
      $composableBuilder(column: $table.titulo, builder: (column) => column);

  GeneratedColumn<bool> get concluidaLocalmente => $composableBuilder(
    column: $table.concluidaLocalmente,
    builder: (column) => column,
  );

  GeneratedColumn<bool> get sincronizadaServidor => $composableBuilder(
    column: $table.sincronizadaServidor,
    builder: (column) => column,
  );
}

class $$CachedAplicacaoTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CachedAplicacaoTable,
          CachedAplicacaoData,
          $$CachedAplicacaoTableFilterComposer,
          $$CachedAplicacaoTableOrderingComposer,
          $$CachedAplicacaoTableAnnotationComposer,
          $$CachedAplicacaoTableCreateCompanionBuilder,
          $$CachedAplicacaoTableUpdateCompanionBuilder,
          (
            CachedAplicacaoData,
            BaseReferences<
              _$AppDatabase,
              $CachedAplicacaoTable,
              CachedAplicacaoData
            >,
          ),
          CachedAplicacaoData,
          PrefetchHooks Function()
        > {
  $$CachedAplicacaoTableTableManager(
    _$AppDatabase db,
    $CachedAplicacaoTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedAplicacaoTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedAplicacaoTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedAplicacaoTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> titulo = const Value.absent(),
                Value<bool> concluidaLocalmente = const Value.absent(),
                Value<bool> sincronizadaServidor = const Value.absent(),
              }) => CachedAplicacaoCompanion(
                id: id,
                titulo: titulo,
                concluidaLocalmente: concluidaLocalmente,
                sincronizadaServidor: sincronizadaServidor,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required String titulo,
                Value<bool> concluidaLocalmente = const Value.absent(),
                Value<bool> sincronizadaServidor = const Value.absent(),
              }) => CachedAplicacaoCompanion.insert(
                id: id,
                titulo: titulo,
                concluidaLocalmente: concluidaLocalmente,
                sincronizadaServidor: sincronizadaServidor,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CachedAplicacaoTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CachedAplicacaoTable,
      CachedAplicacaoData,
      $$CachedAplicacaoTableFilterComposer,
      $$CachedAplicacaoTableOrderingComposer,
      $$CachedAplicacaoTableAnnotationComposer,
      $$CachedAplicacaoTableCreateCompanionBuilder,
      $$CachedAplicacaoTableUpdateCompanionBuilder,
      (
        CachedAplicacaoData,
        BaseReferences<
          _$AppDatabase,
          $CachedAplicacaoTable,
          CachedAplicacaoData
        >,
      ),
      CachedAplicacaoData,
      PrefetchHooks Function()
    >;
typedef $$CachedCategoriaTableCreateCompanionBuilder =
    CachedCategoriaCompanion Function({
      required int id,
      required int aplicacaoId,
      required String titulo,
      Value<String?> mensagemAvatar,
      Value<String?> imagemUrl,
      Value<String?> imagemLocal,
      Value<int> ordem,
      Value<int> rowid,
    });
typedef $$CachedCategoriaTableUpdateCompanionBuilder =
    CachedCategoriaCompanion Function({
      Value<int> id,
      Value<int> aplicacaoId,
      Value<String> titulo,
      Value<String?> mensagemAvatar,
      Value<String?> imagemUrl,
      Value<String?> imagemLocal,
      Value<int> ordem,
      Value<int> rowid,
    });

class $$CachedCategoriaTableFilterComposer
    extends Composer<_$AppDatabase, $CachedCategoriaTable> {
  $$CachedCategoriaTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get aplicacaoId => $composableBuilder(
    column: $table.aplicacaoId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get titulo => $composableBuilder(
    column: $table.titulo,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get mensagemAvatar => $composableBuilder(
    column: $table.mensagemAvatar,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get imagemUrl => $composableBuilder(
    column: $table.imagemUrl,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get imagemLocal => $composableBuilder(
    column: $table.imagemLocal,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get ordem => $composableBuilder(
    column: $table.ordem,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CachedCategoriaTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedCategoriaTable> {
  $$CachedCategoriaTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get aplicacaoId => $composableBuilder(
    column: $table.aplicacaoId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get titulo => $composableBuilder(
    column: $table.titulo,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get mensagemAvatar => $composableBuilder(
    column: $table.mensagemAvatar,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get imagemUrl => $composableBuilder(
    column: $table.imagemUrl,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get imagemLocal => $composableBuilder(
    column: $table.imagemLocal,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get ordem => $composableBuilder(
    column: $table.ordem,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CachedCategoriaTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedCategoriaTable> {
  $$CachedCategoriaTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<int> get aplicacaoId => $composableBuilder(
    column: $table.aplicacaoId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get titulo =>
      $composableBuilder(column: $table.titulo, builder: (column) => column);

  GeneratedColumn<String> get mensagemAvatar => $composableBuilder(
    column: $table.mensagemAvatar,
    builder: (column) => column,
  );

  GeneratedColumn<String> get imagemUrl =>
      $composableBuilder(column: $table.imagemUrl, builder: (column) => column);

  GeneratedColumn<String> get imagemLocal => $composableBuilder(
    column: $table.imagemLocal,
    builder: (column) => column,
  );

  GeneratedColumn<int> get ordem =>
      $composableBuilder(column: $table.ordem, builder: (column) => column);
}

class $$CachedCategoriaTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CachedCategoriaTable,
          CachedCategoriaData,
          $$CachedCategoriaTableFilterComposer,
          $$CachedCategoriaTableOrderingComposer,
          $$CachedCategoriaTableAnnotationComposer,
          $$CachedCategoriaTableCreateCompanionBuilder,
          $$CachedCategoriaTableUpdateCompanionBuilder,
          (
            CachedCategoriaData,
            BaseReferences<
              _$AppDatabase,
              $CachedCategoriaTable,
              CachedCategoriaData
            >,
          ),
          CachedCategoriaData,
          PrefetchHooks Function()
        > {
  $$CachedCategoriaTableTableManager(
    _$AppDatabase db,
    $CachedCategoriaTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedCategoriaTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedCategoriaTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedCategoriaTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<int> aplicacaoId = const Value.absent(),
                Value<String> titulo = const Value.absent(),
                Value<String?> mensagemAvatar = const Value.absent(),
                Value<String?> imagemUrl = const Value.absent(),
                Value<String?> imagemLocal = const Value.absent(),
                Value<int> ordem = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedCategoriaCompanion(
                id: id,
                aplicacaoId: aplicacaoId,
                titulo: titulo,
                mensagemAvatar: mensagemAvatar,
                imagemUrl: imagemUrl,
                imagemLocal: imagemLocal,
                ordem: ordem,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required int id,
                required int aplicacaoId,
                required String titulo,
                Value<String?> mensagemAvatar = const Value.absent(),
                Value<String?> imagemUrl = const Value.absent(),
                Value<String?> imagemLocal = const Value.absent(),
                Value<int> ordem = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedCategoriaCompanion.insert(
                id: id,
                aplicacaoId: aplicacaoId,
                titulo: titulo,
                mensagemAvatar: mensagemAvatar,
                imagemUrl: imagemUrl,
                imagemLocal: imagemLocal,
                ordem: ordem,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CachedCategoriaTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CachedCategoriaTable,
      CachedCategoriaData,
      $$CachedCategoriaTableFilterComposer,
      $$CachedCategoriaTableOrderingComposer,
      $$CachedCategoriaTableAnnotationComposer,
      $$CachedCategoriaTableCreateCompanionBuilder,
      $$CachedCategoriaTableUpdateCompanionBuilder,
      (
        CachedCategoriaData,
        BaseReferences<
          _$AppDatabase,
          $CachedCategoriaTable,
          CachedCategoriaData
        >,
      ),
      CachedCategoriaData,
      PrefetchHooks Function()
    >;
typedef $$CachedPerguntaTableCreateCompanionBuilder =
    CachedPerguntaCompanion Function({
      Value<int> id,
      required int categoriaId,
      required String texto,
      Value<String> tipo,
      Value<bool> obrigatoria,
      Value<String?> imagemUrl,
      Value<String?> imagemLocal,
      Value<int> ordem,
    });
typedef $$CachedPerguntaTableUpdateCompanionBuilder =
    CachedPerguntaCompanion Function({
      Value<int> id,
      Value<int> categoriaId,
      Value<String> texto,
      Value<String> tipo,
      Value<bool> obrigatoria,
      Value<String?> imagemUrl,
      Value<String?> imagemLocal,
      Value<int> ordem,
    });

class $$CachedPerguntaTableFilterComposer
    extends Composer<_$AppDatabase, $CachedPerguntaTable> {
  $$CachedPerguntaTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get categoriaId => $composableBuilder(
    column: $table.categoriaId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get texto => $composableBuilder(
    column: $table.texto,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get tipo => $composableBuilder(
    column: $table.tipo,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<bool> get obrigatoria => $composableBuilder(
    column: $table.obrigatoria,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get imagemUrl => $composableBuilder(
    column: $table.imagemUrl,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get imagemLocal => $composableBuilder(
    column: $table.imagemLocal,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get ordem => $composableBuilder(
    column: $table.ordem,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CachedPerguntaTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedPerguntaTable> {
  $$CachedPerguntaTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get categoriaId => $composableBuilder(
    column: $table.categoriaId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get texto => $composableBuilder(
    column: $table.texto,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get tipo => $composableBuilder(
    column: $table.tipo,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<bool> get obrigatoria => $composableBuilder(
    column: $table.obrigatoria,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get imagemUrl => $composableBuilder(
    column: $table.imagemUrl,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get imagemLocal => $composableBuilder(
    column: $table.imagemLocal,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get ordem => $composableBuilder(
    column: $table.ordem,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CachedPerguntaTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedPerguntaTable> {
  $$CachedPerguntaTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<int> get categoriaId => $composableBuilder(
    column: $table.categoriaId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get texto =>
      $composableBuilder(column: $table.texto, builder: (column) => column);

  GeneratedColumn<String> get tipo =>
      $composableBuilder(column: $table.tipo, builder: (column) => column);

  GeneratedColumn<bool> get obrigatoria => $composableBuilder(
    column: $table.obrigatoria,
    builder: (column) => column,
  );

  GeneratedColumn<String> get imagemUrl =>
      $composableBuilder(column: $table.imagemUrl, builder: (column) => column);

  GeneratedColumn<String> get imagemLocal => $composableBuilder(
    column: $table.imagemLocal,
    builder: (column) => column,
  );

  GeneratedColumn<int> get ordem =>
      $composableBuilder(column: $table.ordem, builder: (column) => column);
}

class $$CachedPerguntaTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CachedPerguntaTable,
          CachedPerguntaData,
          $$CachedPerguntaTableFilterComposer,
          $$CachedPerguntaTableOrderingComposer,
          $$CachedPerguntaTableAnnotationComposer,
          $$CachedPerguntaTableCreateCompanionBuilder,
          $$CachedPerguntaTableUpdateCompanionBuilder,
          (
            CachedPerguntaData,
            BaseReferences<
              _$AppDatabase,
              $CachedPerguntaTable,
              CachedPerguntaData
            >,
          ),
          CachedPerguntaData,
          PrefetchHooks Function()
        > {
  $$CachedPerguntaTableTableManager(
    _$AppDatabase db,
    $CachedPerguntaTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedPerguntaTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedPerguntaTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedPerguntaTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<int> categoriaId = const Value.absent(),
                Value<String> texto = const Value.absent(),
                Value<String> tipo = const Value.absent(),
                Value<bool> obrigatoria = const Value.absent(),
                Value<String?> imagemUrl = const Value.absent(),
                Value<String?> imagemLocal = const Value.absent(),
                Value<int> ordem = const Value.absent(),
              }) => CachedPerguntaCompanion(
                id: id,
                categoriaId: categoriaId,
                texto: texto,
                tipo: tipo,
                obrigatoria: obrigatoria,
                imagemUrl: imagemUrl,
                imagemLocal: imagemLocal,
                ordem: ordem,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required int categoriaId,
                required String texto,
                Value<String> tipo = const Value.absent(),
                Value<bool> obrigatoria = const Value.absent(),
                Value<String?> imagemUrl = const Value.absent(),
                Value<String?> imagemLocal = const Value.absent(),
                Value<int> ordem = const Value.absent(),
              }) => CachedPerguntaCompanion.insert(
                id: id,
                categoriaId: categoriaId,
                texto: texto,
                tipo: tipo,
                obrigatoria: obrigatoria,
                imagemUrl: imagemUrl,
                imagemLocal: imagemLocal,
                ordem: ordem,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CachedPerguntaTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CachedPerguntaTable,
      CachedPerguntaData,
      $$CachedPerguntaTableFilterComposer,
      $$CachedPerguntaTableOrderingComposer,
      $$CachedPerguntaTableAnnotationComposer,
      $$CachedPerguntaTableCreateCompanionBuilder,
      $$CachedPerguntaTableUpdateCompanionBuilder,
      (
        CachedPerguntaData,
        BaseReferences<_$AppDatabase, $CachedPerguntaTable, CachedPerguntaData>,
      ),
      CachedPerguntaData,
      PrefetchHooks Function()
    >;
typedef $$CachedOpcaoTableCreateCompanionBuilder =
    CachedOpcaoCompanion Function({
      Value<int> id,
      required int perguntaId,
      required String descricao,
      Value<String?> emoji,
      Value<int> ordem,
    });
typedef $$CachedOpcaoTableUpdateCompanionBuilder =
    CachedOpcaoCompanion Function({
      Value<int> id,
      Value<int> perguntaId,
      Value<String> descricao,
      Value<String?> emoji,
      Value<int> ordem,
    });

class $$CachedOpcaoTableFilterComposer
    extends Composer<_$AppDatabase, $CachedOpcaoTable> {
  $$CachedOpcaoTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get perguntaId => $composableBuilder(
    column: $table.perguntaId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get descricao => $composableBuilder(
    column: $table.descricao,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get emoji => $composableBuilder(
    column: $table.emoji,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get ordem => $composableBuilder(
    column: $table.ordem,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CachedOpcaoTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedOpcaoTable> {
  $$CachedOpcaoTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get perguntaId => $composableBuilder(
    column: $table.perguntaId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get descricao => $composableBuilder(
    column: $table.descricao,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get emoji => $composableBuilder(
    column: $table.emoji,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get ordem => $composableBuilder(
    column: $table.ordem,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CachedOpcaoTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedOpcaoTable> {
  $$CachedOpcaoTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<int> get perguntaId => $composableBuilder(
    column: $table.perguntaId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get descricao =>
      $composableBuilder(column: $table.descricao, builder: (column) => column);

  GeneratedColumn<String> get emoji =>
      $composableBuilder(column: $table.emoji, builder: (column) => column);

  GeneratedColumn<int> get ordem =>
      $composableBuilder(column: $table.ordem, builder: (column) => column);
}

class $$CachedOpcaoTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CachedOpcaoTable,
          CachedOpcaoData,
          $$CachedOpcaoTableFilterComposer,
          $$CachedOpcaoTableOrderingComposer,
          $$CachedOpcaoTableAnnotationComposer,
          $$CachedOpcaoTableCreateCompanionBuilder,
          $$CachedOpcaoTableUpdateCompanionBuilder,
          (
            CachedOpcaoData,
            BaseReferences<_$AppDatabase, $CachedOpcaoTable, CachedOpcaoData>,
          ),
          CachedOpcaoData,
          PrefetchHooks Function()
        > {
  $$CachedOpcaoTableTableManager(_$AppDatabase db, $CachedOpcaoTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedOpcaoTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedOpcaoTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedOpcaoTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<int> perguntaId = const Value.absent(),
                Value<String> descricao = const Value.absent(),
                Value<String?> emoji = const Value.absent(),
                Value<int> ordem = const Value.absent(),
              }) => CachedOpcaoCompanion(
                id: id,
                perguntaId: perguntaId,
                descricao: descricao,
                emoji: emoji,
                ordem: ordem,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required int perguntaId,
                required String descricao,
                Value<String?> emoji = const Value.absent(),
                Value<int> ordem = const Value.absent(),
              }) => CachedOpcaoCompanion.insert(
                id: id,
                perguntaId: perguntaId,
                descricao: descricao,
                emoji: emoji,
                ordem: ordem,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CachedOpcaoTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CachedOpcaoTable,
      CachedOpcaoData,
      $$CachedOpcaoTableFilterComposer,
      $$CachedOpcaoTableOrderingComposer,
      $$CachedOpcaoTableAnnotationComposer,
      $$CachedOpcaoTableCreateCompanionBuilder,
      $$CachedOpcaoTableUpdateCompanionBuilder,
      (
        CachedOpcaoData,
        BaseReferences<_$AppDatabase, $CachedOpcaoTable, CachedOpcaoData>,
      ),
      CachedOpcaoData,
      PrefetchHooks Function()
    >;
typedef $$OutboxRespostaTableCreateCompanionBuilder =
    OutboxRespostaCompanion Function({
      required String clientUuid,
      required int aplicacaoId,
      required int perguntaId,
      Value<String> status,
      required String payload,
      required DateTime criadoEm,
      Value<int> rowid,
    });
typedef $$OutboxRespostaTableUpdateCompanionBuilder =
    OutboxRespostaCompanion Function({
      Value<String> clientUuid,
      Value<int> aplicacaoId,
      Value<int> perguntaId,
      Value<String> status,
      Value<String> payload,
      Value<DateTime> criadoEm,
      Value<int> rowid,
    });

class $$OutboxRespostaTableFilterComposer
    extends Composer<_$AppDatabase, $OutboxRespostaTable> {
  $$OutboxRespostaTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get clientUuid => $composableBuilder(
    column: $table.clientUuid,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get aplicacaoId => $composableBuilder(
    column: $table.aplicacaoId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get perguntaId => $composableBuilder(
    column: $table.perguntaId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get payload => $composableBuilder(
    column: $table.payload,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get criadoEm => $composableBuilder(
    column: $table.criadoEm,
    builder: (column) => ColumnFilters(column),
  );
}

class $$OutboxRespostaTableOrderingComposer
    extends Composer<_$AppDatabase, $OutboxRespostaTable> {
  $$OutboxRespostaTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get clientUuid => $composableBuilder(
    column: $table.clientUuid,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get aplicacaoId => $composableBuilder(
    column: $table.aplicacaoId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get perguntaId => $composableBuilder(
    column: $table.perguntaId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get payload => $composableBuilder(
    column: $table.payload,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get criadoEm => $composableBuilder(
    column: $table.criadoEm,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$OutboxRespostaTableAnnotationComposer
    extends Composer<_$AppDatabase, $OutboxRespostaTable> {
  $$OutboxRespostaTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get clientUuid => $composableBuilder(
    column: $table.clientUuid,
    builder: (column) => column,
  );

  GeneratedColumn<int> get aplicacaoId => $composableBuilder(
    column: $table.aplicacaoId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get perguntaId => $composableBuilder(
    column: $table.perguntaId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<String> get payload =>
      $composableBuilder(column: $table.payload, builder: (column) => column);

  GeneratedColumn<DateTime> get criadoEm =>
      $composableBuilder(column: $table.criadoEm, builder: (column) => column);
}

class $$OutboxRespostaTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $OutboxRespostaTable,
          OutboxRespostaData,
          $$OutboxRespostaTableFilterComposer,
          $$OutboxRespostaTableOrderingComposer,
          $$OutboxRespostaTableAnnotationComposer,
          $$OutboxRespostaTableCreateCompanionBuilder,
          $$OutboxRespostaTableUpdateCompanionBuilder,
          (
            OutboxRespostaData,
            BaseReferences<
              _$AppDatabase,
              $OutboxRespostaTable,
              OutboxRespostaData
            >,
          ),
          OutboxRespostaData,
          PrefetchHooks Function()
        > {
  $$OutboxRespostaTableTableManager(
    _$AppDatabase db,
    $OutboxRespostaTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$OutboxRespostaTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$OutboxRespostaTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$OutboxRespostaTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> clientUuid = const Value.absent(),
                Value<int> aplicacaoId = const Value.absent(),
                Value<int> perguntaId = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<String> payload = const Value.absent(),
                Value<DateTime> criadoEm = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => OutboxRespostaCompanion(
                clientUuid: clientUuid,
                aplicacaoId: aplicacaoId,
                perguntaId: perguntaId,
                status: status,
                payload: payload,
                criadoEm: criadoEm,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String clientUuid,
                required int aplicacaoId,
                required int perguntaId,
                Value<String> status = const Value.absent(),
                required String payload,
                required DateTime criadoEm,
                Value<int> rowid = const Value.absent(),
              }) => OutboxRespostaCompanion.insert(
                clientUuid: clientUuid,
                aplicacaoId: aplicacaoId,
                perguntaId: perguntaId,
                status: status,
                payload: payload,
                criadoEm: criadoEm,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$OutboxRespostaTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $OutboxRespostaTable,
      OutboxRespostaData,
      $$OutboxRespostaTableFilterComposer,
      $$OutboxRespostaTableOrderingComposer,
      $$OutboxRespostaTableAnnotationComposer,
      $$OutboxRespostaTableCreateCompanionBuilder,
      $$OutboxRespostaTableUpdateCompanionBuilder,
      (
        OutboxRespostaData,
        BaseReferences<_$AppDatabase, $OutboxRespostaTable, OutboxRespostaData>,
      ),
      OutboxRespostaData,
      PrefetchHooks Function()
    >;
typedef $$LocalProgressTableCreateCompanionBuilder =
    LocalProgressCompanion Function({
      required int aplicacaoId,
      required int perguntaId,
      required int categoriaId,
      required String respostaJson,
      required DateTime respondidoEm,
      Value<int> rowid,
    });
typedef $$LocalProgressTableUpdateCompanionBuilder =
    LocalProgressCompanion Function({
      Value<int> aplicacaoId,
      Value<int> perguntaId,
      Value<int> categoriaId,
      Value<String> respostaJson,
      Value<DateTime> respondidoEm,
      Value<int> rowid,
    });

class $$LocalProgressTableFilterComposer
    extends Composer<_$AppDatabase, $LocalProgressTable> {
  $$LocalProgressTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get aplicacaoId => $composableBuilder(
    column: $table.aplicacaoId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get perguntaId => $composableBuilder(
    column: $table.perguntaId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get categoriaId => $composableBuilder(
    column: $table.categoriaId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get respostaJson => $composableBuilder(
    column: $table.respostaJson,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get respondidoEm => $composableBuilder(
    column: $table.respondidoEm,
    builder: (column) => ColumnFilters(column),
  );
}

class $$LocalProgressTableOrderingComposer
    extends Composer<_$AppDatabase, $LocalProgressTable> {
  $$LocalProgressTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get aplicacaoId => $composableBuilder(
    column: $table.aplicacaoId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get perguntaId => $composableBuilder(
    column: $table.perguntaId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get categoriaId => $composableBuilder(
    column: $table.categoriaId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get respostaJson => $composableBuilder(
    column: $table.respostaJson,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get respondidoEm => $composableBuilder(
    column: $table.respondidoEm,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$LocalProgressTableAnnotationComposer
    extends Composer<_$AppDatabase, $LocalProgressTable> {
  $$LocalProgressTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get aplicacaoId => $composableBuilder(
    column: $table.aplicacaoId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get perguntaId => $composableBuilder(
    column: $table.perguntaId,
    builder: (column) => column,
  );

  GeneratedColumn<int> get categoriaId => $composableBuilder(
    column: $table.categoriaId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get respostaJson => $composableBuilder(
    column: $table.respostaJson,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get respondidoEm => $composableBuilder(
    column: $table.respondidoEm,
    builder: (column) => column,
  );
}

class $$LocalProgressTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $LocalProgressTable,
          LocalProgressData,
          $$LocalProgressTableFilterComposer,
          $$LocalProgressTableOrderingComposer,
          $$LocalProgressTableAnnotationComposer,
          $$LocalProgressTableCreateCompanionBuilder,
          $$LocalProgressTableUpdateCompanionBuilder,
          (
            LocalProgressData,
            BaseReferences<
              _$AppDatabase,
              $LocalProgressTable,
              LocalProgressData
            >,
          ),
          LocalProgressData,
          PrefetchHooks Function()
        > {
  $$LocalProgressTableTableManager(_$AppDatabase db, $LocalProgressTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$LocalProgressTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$LocalProgressTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$LocalProgressTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> aplicacaoId = const Value.absent(),
                Value<int> perguntaId = const Value.absent(),
                Value<int> categoriaId = const Value.absent(),
                Value<String> respostaJson = const Value.absent(),
                Value<DateTime> respondidoEm = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => LocalProgressCompanion(
                aplicacaoId: aplicacaoId,
                perguntaId: perguntaId,
                categoriaId: categoriaId,
                respostaJson: respostaJson,
                respondidoEm: respondidoEm,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required int aplicacaoId,
                required int perguntaId,
                required int categoriaId,
                required String respostaJson,
                required DateTime respondidoEm,
                Value<int> rowid = const Value.absent(),
              }) => LocalProgressCompanion.insert(
                aplicacaoId: aplicacaoId,
                perguntaId: perguntaId,
                categoriaId: categoriaId,
                respostaJson: respostaJson,
                respondidoEm: respondidoEm,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$LocalProgressTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $LocalProgressTable,
      LocalProgressData,
      $$LocalProgressTableFilterComposer,
      $$LocalProgressTableOrderingComposer,
      $$LocalProgressTableAnnotationComposer,
      $$LocalProgressTableCreateCompanionBuilder,
      $$LocalProgressTableUpdateCompanionBuilder,
      (
        LocalProgressData,
        BaseReferences<_$AppDatabase, $LocalProgressTable, LocalProgressData>,
      ),
      LocalProgressData,
      PrefetchHooks Function()
    >;

class $AppDatabaseManager {
  final _$AppDatabase _db;
  $AppDatabaseManager(this._db);
  $$CachedAplicacaoTableTableManager get cachedAplicacao =>
      $$CachedAplicacaoTableTableManager(_db, _db.cachedAplicacao);
  $$CachedCategoriaTableTableManager get cachedCategoria =>
      $$CachedCategoriaTableTableManager(_db, _db.cachedCategoria);
  $$CachedPerguntaTableTableManager get cachedPergunta =>
      $$CachedPerguntaTableTableManager(_db, _db.cachedPergunta);
  $$CachedOpcaoTableTableManager get cachedOpcao =>
      $$CachedOpcaoTableTableManager(_db, _db.cachedOpcao);
  $$OutboxRespostaTableTableManager get outboxResposta =>
      $$OutboxRespostaTableTableManager(_db, _db.outboxResposta);
  $$LocalProgressTableTableManager get localProgress =>
      $$LocalProgressTableTableManager(_db, _db.localProgress);
}
