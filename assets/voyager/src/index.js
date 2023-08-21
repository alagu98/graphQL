import React from 'react';
import ReactDOM from 'react-dom';
import { Voyager } from 'graphql-voyager';
import Drupal from 'drupal';

/**
 * Behavior for rendering the GraphQL Voyager interface.
 */
Drupal.behaviors.graphQLRenderVoyager = {
  attach: (context, settings) => {
    const container = once('#graphql-voyager', context) || undefined;

    if (typeof container === 'undefined') {
      return;
    }

    ReactDOM.render(
      <Voyager
        introspection={settings.graphqlIntrospectionData}
        displayOptions={{ skipRelay: true, sortByAlphabet: true }}
      />,
      container
    );
  },
};
