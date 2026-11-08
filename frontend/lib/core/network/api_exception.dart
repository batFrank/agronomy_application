import 'package:dio/dio.dart';

/// Error surfaced to the UI. Backend errors look like `{error, message}`.
class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  factory ApiException.fromDio(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['message'] is String) {
      return ApiException(
        data['message'] as String,
        statusCode: e.response?.statusCode,
      );
    }
    return switch (e.type) {
      DioExceptionType.connectionError ||
      DioExceptionType.connectionTimeout ||
      DioExceptionType.receiveTimeout ||
      DioExceptionType.sendTimeout => const ApiException(
        'No se pudo conectar con el servidor',
      ),
      _ => ApiException('Error inesperado', statusCode: e.response?.statusCode),
    };
  }

  @override
  String toString() => message;
}
