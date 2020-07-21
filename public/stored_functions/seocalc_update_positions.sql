DROP FUNCTION seocalc_update_positions(VARCHAR(255), VARCHAR[]);

CREATE FUNCTION seocalc_update_positions(VARCHAR(255), VARCHAR[]) RETURNS VOID AS $$
DECLARE
  dom ALIAS FOR $1;
  arr_phrases ALIAS FOR $2;
  p VARCHAR[];
  id_domain INT;
BEGIN
  SELECT id INTO id_domain FROM domain WHERE domain.domain=dom;
  IF arr_phrases <> '{}' THEN
    FOREACH p SLICE 1 IN ARRAY arr_phrases
    LOOP
      UPDATE sem_yadro SET position=cast(p[2] AS SMALLINT), position_google=cast(p[3] AS SMALLINT), position_update=date(CURRENT_TIMESTAMP), position_google_update=date(CURRENT_TIMESTAMP) WHERE domain_id=id_domain AND phrase_id=cast(p[1] AS INT);
    END LOOP;
  END IF;
  UPDATE domain SET is_position=TRUE WHERE id=id_domain;
END;
$$ LANGUAGE plpgsql;