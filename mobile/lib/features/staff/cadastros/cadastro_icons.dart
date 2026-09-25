import 'package:flutter/material.dart';

IconData iconeDoCadastro(String key) => switch (key) {
  'escolas' => Icons.school_outlined,
  'usuarios' => Icons.manage_accounts_outlined,
  'vinculos' => Icons.badge_outlined,
  'professores-turmas' => Icons.co_present_outlined,
  'series' => Icons.stairs_outlined,
  'turmas' => Icons.groups_2_outlined,
  'alunos' => Icons.face_outlined,
  'questionarios' => Icons.assignment_outlined,
  'categorias' => Icons.folder_outlined,
  'subcategorias' => Icons.folder_open_outlined,
  'perguntas' => Icons.help_outline_rounded,
  'opcoes' => Icons.radio_button_checked_outlined,
  'regras' => Icons.rule_rounded,
  'aplicacoes' => Icons.play_circle_outline_rounded,
  'respostas' => Icons.question_answer_outlined,
  'resultados' => Icons.insights_outlined,
  'termos' => Icons.verified_user_outlined,
  'avatares' => Icons.emoji_emotions_outlined,
  _ => Icons.list_alt_rounded,
};
