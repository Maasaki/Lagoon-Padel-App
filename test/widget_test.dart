import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('smoke — widget de base', (WidgetTester tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: Center(child: Text('Lagoon Padel')),
        ),
      ),
    );
    expect(find.text('Lagoon Padel'), findsOneWidget);
  });
}
