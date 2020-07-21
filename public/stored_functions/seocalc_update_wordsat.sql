DROP FUNCTION seocalc_update_wordstat(VARCHAR(255), VARCHAR[]);

CREATE FUNCTION seocalc_update_wordstat(VARCHAR(255), VARCHAR[]) RETURNS VOID AS $$
DECLARE
  dom ALIAS FOR $1;
  arr_phrases ALIAS FOR $2;
  p VARCHAR[];
  id_domain INT;
  accurate SMALLINT;
BEGIN
  SELECT id INTO id_domain FROM domain WHERE domain.domain=dom;
  IF arr_phrases <> '{}' THEN
    FOREACH p SLICE 1 IN ARRAY arr_phrases
    LOOP
      accurate := cast(p[3] AS SMALLINT);
      IF accurate = 1 THEN
        UPDATE wordstat SET frequency=cast(p[2] AS INT), frequency_update=date(CURRENT_TIMESTAMP) WHERE id=cast(p[1] AS INTEGER);
      ELSE
        UPDATE wordstat SET common_frequency=cast(p[2] AS INT), common_frequency_update=date(CURRENT_TIMESTAMP) WHERE id=cast(p[1] AS INTEGER);
      END IF;
    END LOOP;
  END IF;
  UPDATE domain SET is_wordstat=TRUE WHERE id=id_domain;
END;
$$ LANGUAGE plpgsql;