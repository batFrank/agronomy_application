import 'package:agronomy_app/core/network/auth_interceptor.dart';
import 'package:agronomy_app/core/storage/token_storage.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../support/fakes.dart';

/// Answers requests from a closure instead of the network.
class _Adapter implements HttpClientAdapter {
  _Adapter(this.handler);

  final ResponseBody Function(RequestOptions) handler;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async => handler(options);

  @override
  void close({bool force = false}) {}
}

ResponseBody _json(int status, String body) => ResponseBody.fromString(
  body,
  status,
  headers: {
    Headers.contentTypeHeader: ['application/json'],
  },
);

void main() {
  late InMemoryTokenStorage storage;
  late Dio dio;
  late List<String> calls;
  late int refreshCalls;
  late int expired;

  setUp(() {
    storage = InMemoryTokenStorage()
      ..tokens = const AuthTokens(token: 'old', refreshToken: 'r1');
    calls = [];
    refreshCalls = 0;
    expired = 0;

    final refreshDio = Dio(BaseOptions(baseUrl: 'http://api'))
      ..httpClientAdapter = _Adapter((o) {
        refreshCalls++;
        return refreshCalls == 1 && storage.tokens?.refreshToken == 'r1'
            ? _json(200, '{"data":{"token":"new","refresh_token":"r2"}}')
            : _json(401, '{"error":"Invalid refresh token","message":"used"}');
      });

    dio = Dio(BaseOptions(baseUrl: 'http://api'))
      ..httpClientAdapter = _Adapter((o) {
        calls.add('${o.path} ${o.headers['Authorization']}');
        return o.headers['Authorization'] == 'Bearer new'
            ? _json(200, '{"data":{}}')
            : _json(401, '{"message":"expired"}');
      });
    dio.interceptors.add(
      AuthInterceptor(
        dio: dio,
        refreshDio: refreshDio,
        storage: storage,
        onSessionExpired: () => expired++,
      ),
    );
  });

  test('retries once with the renewed token after a 401', () async {
    final res = await dio.get<dynamic>('/me');

    expect(res.statusCode, 200);
    expect(calls, ['/me Bearer old', '/me Bearer new']);
    expect(storage.tokens?.refreshToken, 'r2');
    expect(expired, 0);
  });

  test('concurrent 401s share a single refresh call', () async {
    await Future.wait([dio.get<dynamic>('/me'), dio.get<dynamic>('/other')]);

    expect(refreshCalls, 1);
  });

  test('clears the session when the refresh token is rejected', () async {
    storage.tokens = const AuthTokens(token: 'old', refreshToken: 'spent');

    await expectLater(dio.get<dynamic>('/me'), throwsA(isA<DioException>()));

    expect(storage.tokens, isNull);
    expect(expired, 1);
  });
}
